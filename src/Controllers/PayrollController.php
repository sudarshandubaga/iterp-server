<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Throwable;

/**
 * Complete Payroll Module Controller:
 * 1. Salary Slabs (with auto-generated slab label & drag-and-drop sort order)
 * 2. Allowance & Deductions (with Apply On, Calculate On & drag-and-drop sort order)
 * 3. Payroll Settings (PF, ESI, TDS)
 * 4. Part Payments (Salary Advance per employee & month)
 * 5. Loans (auto EMI, Pay by Cash, View Transactions, auto-deduct in payslip)
 * 6. Generate & View Pay Slips (Firm + Month + Multi-Employee)
 */
class PayrollController
{
    /**
     * Self-healing DDL check so all Payroll tables exist automatically on deploy.
     */
    public static function ensureTables(\PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_salary_slabs (
                    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                    lower_limit       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    upper_limit       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    increment_percent DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
                    slab              VARCHAR(100)  NOT NULL,
                    sort_order        INT UNSIGNED  NOT NULL DEFAULT 0,
                    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at        TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_pss_firm (firm_id),
                    INDEX idx_pss_sort (sort_order),
                    INDEX idx_pss_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_components (
                    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id    BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                    name         VARCHAR(255) NOT NULL,
                    short_name   VARCHAR(50)  NOT NULL,
                    nature       ENUM("allowance","deduction") NOT NULL DEFAULT "allowance",
                    type         ENUM("percentage","fixed") NOT NULL DEFAULT "fixed",
                    value        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    apply_on     VARCHAR(50)  NOT NULL DEFAULT "basic",
                    calculate_on ENUM("basic","working_days") NOT NULL DEFAULT "basic",
                    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
                    is_active    ENUM("y","n") NOT NULL DEFAULT "y",
                    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at   TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_pc_firm (firm_id),
                    INDEX idx_pc_nature (nature),
                    INDEX idx_pc_sort (sort_order),
                    INDEX idx_pc_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_settings (
                    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id           BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    pf_pension_rate   DECIMAL(6,2)  NOT NULL DEFAULT 8.33,
                    pf_epf_rate       DECIMAL(6,2)  NOT NULL DEFAULT 3.67,
                    pf_employee_share DECIMAL(6,2)  NOT NULL DEFAULT 12.00,
                    pf_employer_share DECIMAL(6,2)  NOT NULL DEFAULT 12.00,
                    pf_max_amount     DECIMAL(12,2) NOT NULL DEFAULT 15000.00,
                    esi_employee_rate DECIMAL(6,2)  NOT NULL DEFAULT 0.75,
                    esi_employer_rate DECIMAL(6,2)  NOT NULL DEFAULT 3.25,
                    esi_max_amount    DECIMAL(12,2) NOT NULL DEFAULT 21000.00,
                    tds_percentage    DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
                    updated_by        BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_payroll_settings_firm (firm_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_employee_profiles (
                    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    employee_id  BIGINT UNSIGNED NOT NULL,
                    firm_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    apply_pf     ENUM("y","n") NOT NULL DEFAULT "y",
                    apply_esi    ENUM("y","n") NOT NULL DEFAULT "n",
                    apply_tds    ENUM("y","n") NOT NULL DEFAULT "n",
                    bank_name    VARCHAR(150) NULL DEFAULT NULL,
                    account_no   VARCHAR(80)  NULL DEFAULT NULL,
                    ifsc_code    VARCHAR(40)  NULL DEFAULT NULL,
                    pan_no       VARCHAR(40)  NULL DEFAULT NULL,
                    uan_no       VARCHAR(50)  NULL DEFAULT NULL,
                    esi_no       VARCHAR(50)  NULL DEFAULT NULL,
                    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_pep_employee (employee_id),
                    INDEX idx_pep_firm (firm_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_part_payments (
                    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id    BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                    employee_id  BIGINT UNSIGNED NOT NULL,
                    amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    paid_date    DATE NOT NULL,
                    salary_month VARCHAR(7) NOT NULL,
                    description  TEXT NULL,
                    created_by   BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at   TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_ppp_firm (firm_id),
                    INDEX idx_ppp_emp_month (employee_id, salary_month),
                    INDEX idx_ppp_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_loans (
                    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id        BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id          BIGINT UNSIGNED NULL DEFAULT NULL,
                    employee_id      BIGINT UNSIGNED NOT NULL,
                    loan_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    paid_date        DATE NOT NULL,
                    slab             INT UNSIGNED NOT NULL DEFAULT 1,
                    emi_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    paid_emi         INT UNSIGNED NOT NULL DEFAULT 0,
                    remaining_emi    INT UNSIGNED NOT NULL DEFAULT 0,
                    paid_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    status           ENUM("active","closed") NOT NULL DEFAULT "active",
                    description      TEXT NULL,
                    created_by       BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at       TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_pl_firm (firm_id),
                    INDEX idx_pl_emp (employee_id),
                    INDEX idx_pl_status (status),
                    INDEX idx_pl_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_loan_transactions (
                    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    loan_id      BIGINT UNSIGNED NOT NULL,
                    employee_id  BIGINT UNSIGNED NOT NULL,
                    payment_mode ENUM("cash","salary_deduction") NOT NULL DEFAULT "cash",
                    emi_count    INT UNSIGNED NOT NULL DEFAULT 1,
                    amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    payment_date DATE NOT NULL,
                    salary_month VARCHAR(7) NULL DEFAULT NULL,
                    payslip_id   BIGINT UNSIGNED NULL DEFAULT NULL,
                    remarks      VARCHAR(500) NULL DEFAULT NULL,
                    created_by   BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_plt_loan (loan_id),
                    INDEX idx_plt_emp (employee_id),
                    INDEX idx_plt_month (salary_month),
                    INDEX idx_plt_payslip (payslip_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS payroll_payslips (
                    id                         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id                  BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id                    BIGINT UNSIGNED NULL DEFAULT NULL,
                    employee_id                BIGINT UNSIGNED NOT NULL,
                    salary_month               VARCHAR(7) NOT NULL,
                    total_days                 INT UNSIGNED NOT NULL DEFAULT 30,
                    working_days               DECIMAL(6,2) NOT NULL DEFAULT 30.00,
                    present_days               DECIMAL(6,2) NOT NULL DEFAULT 30.00,
                    absent_days                DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                    basic_salary               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    earned_basic               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    allowances_json            TEXT NULL,
                    deductions_json            TEXT NULL,
                    total_allowances           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    total_component_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    gross_salary               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    pf_employee                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    pf_employer                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    pf_pension                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    pf_epf                     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    esi_employee               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    esi_employer               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    tds_amount                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    part_payment_deduction     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    loan_deduction             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    total_deductions           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    net_salary                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    status                     ENUM("generated","paid") NOT NULL DEFAULT "generated",
                    generated_by               BIGINT UNSIGNED NULL DEFAULT NULL,
                    generated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_payslip_emp_month (employee_id, salary_month),
                    INDEX idx_ps_firm_month (firm_id, salary_month),
                    INDEX idx_ps_emp (employee_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            try {
                $cols = $pdo->query("SHOW COLUMNS FROM payroll_employee_profiles LIKE 'esi_no'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE payroll_employee_profiles ADD COLUMN esi_no VARCHAR(50) NULL DEFAULT NULL AFTER uan_no");
                }
            } catch (Throwable $e) {
            }
        } catch (Throwable $e) {
            // Best-effort self-healing
        }
    }

    protected function formatSlabLabel(float $lower, float $upper): string
    {
        $l = fmod($lower, 1.0) === 0.0 ? (string) (int) $lower : rtrim(rtrim(number_format($lower, 2, '.', ''), '0'), '.');
        $u = fmod($upper, 1.0) === 0.0 ? (string) (int) $upper : rtrim(rtrim(number_format($upper, 2, '.', ''), '0'), '.');
        return "{$l} - {$u}";
    }

    protected function resolveFirmId(Request $request, array $data = []): ?int
    {
        $raw = $data['firm_id'] ?? $request->query('firm_id');
        if ($raw === null || $raw === '' || (int) $raw <= 0) {
            return null;
        }
        return (int) $raw;
    }

    protected function authMeta(array $context): array
    {
        $u = $context['user'] ?? null;
        return [
            'user_id'   => $u ? (int) $u->id : null,
            'tenant_id' => ($u && isset($u->tenant_id) && $u->tenant_id) ? (int) $u->tenant_id : null,
            'firm_id'   => ($u && isset($u->firm_id) && $u->firm_id) ? (int) $u->firm_id : null,
        ];
    }

    // =========================================================================
    // 0. PAYROLL EMPLOYEES LIST (with employee_id + salary profile)
    // =========================================================================

    public function employeesList(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $firmId = $this->resolveFirmId($request);
        $bindings = [];
        $where = "WHERE u.deleted_at IS NULL AND e.deleted_at IS NULL AND u.is_active = 'y'";
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }

        $sql = "SELECT e.id AS employee_id, u.id AS user_id, u.first_name, u.middle_name, u.last_name,
                       u.email, u.mobile_no, u.firm_id, e.employee_type, e.attendance_code, e.photo,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                       COALESCE(pep.basic_salary, 0) AS basic_salary,
                       COALESCE(pep.apply_pf, 'y') AS apply_pf,
                       COALESCE(pep.apply_esi, 'n') AS apply_esi,
                       COALESCE(pep.apply_tds, 'n') AS apply_tds
                FROM employees e
                INNER JOIN users u ON u.id = e.user_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                LEFT JOIN payroll_employee_profiles pep ON pep.employee_id = e.id
                {$where}
                GROUP BY e.id, u.id, pep.id
                ORDER BY u.first_name ASC, u.last_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = array_map(static function (array $r): array {
            $name = trim(implode(' ', array_filter([$r['first_name'] ?? '', $r['middle_name'] ?? '', $r['last_name'] ?? ''])));
            return [
                'employee_id'     => (int) $r['employee_id'],
                'user_id'         => (int) $r['user_id'],
                'employee_name'   => $name,
                'employee_type'   => (string) ($r['employee_type'] ?? 'Teaching'),
                'attendance_code' => $r['attendance_code'] ?? '',
                'role_names'      => $r['role_names'] ?? '',
                'mobile_no'       => $r['mobile_no'] ?? '',
                'email'           => $r['email'] ?? '',
                'firm_id'         => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'photo'           => $r['photo'] ?: null,
                'basic_salary'    => (float) $r['basic_salary'],
                'apply_pf'        => (string) $r['apply_pf'],
                'apply_esi'       => (string) $r['apply_esi'],
                'apply_tds'       => (string) $r['apply_tds'],
            ];
        }, $rows);

        return Response::success($items);
    }

    // =========================================================================
    // 1. SALARY SLABS
    // =========================================================================

    public function salarySlabsIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $firmId = $this->resolveFirmId($request);
        $bindings = [];
        $where = 'WHERE deleted_at IS NULL';
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (firm_id = :firm_id OR firm_id IS NULL)';
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM payroll_salary_slabs {$where} ORDER BY sort_order ASC, lower_limit ASC, id ASC"
        );
        $stmt->execute($bindings);
        $rows = array_map(static function (array $r): array {
            return [
                'id'                => (int) $r['id'],
                'firm_id'           => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'lower_limit'       => (float) $r['lower_limit'],
                'upper_limit'       => (float) $r['upper_limit'],
                'increment_percent' => (float) $r['increment_percent'],
                'slab'              => (string) $r['slab'],
                'sort_order'        => (int) $r['sort_order'],
            ];
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

        return Response::success($rows);
    }

    public function salarySlabsStore(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $data = $request->all();

        $lower = isset($data['lower_limit']) && $data['lower_limit'] !== '' ? (float) $data['lower_limit'] : null;
        $upper = isset($data['upper_limit']) && $data['upper_limit'] !== '' ? (float) $data['upper_limit'] : null;
        $inc = isset($data['increment_percent']) && $data['increment_percent'] !== '' ? (float) $data['increment_percent'] : 0.0;

        if ($lower === null || $upper === null) {
            return Response::error('Lower Limit and Upper Limit are required.', 422);
        }
        if ($upper < $lower) {
            return Response::error('Upper Limit must be greater than or equal to Lower Limit.', 422);
        }

        $slabLabel = !empty($data['slab']) ? trim((string) $data['slab']) : $this->formatSlabLabel($lower, $upper);
        $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== ''
            ? (int) $data['sort_order']
            : ((int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM payroll_salary_slabs WHERE deleted_at IS NULL')->fetchColumn() + 1);

        $meta = $this->authMeta($context);
        $firmId = $this->resolveFirmId($request, $data) ?? $meta['firm_id'];

        $stmt = $pdo->prepare(
            'INSERT INTO payroll_salary_slabs (tenant_id, firm_id, lower_limit, upper_limit, increment_percent, slab, sort_order)
             VALUES (:tenant_id, :firm_id, :lower_limit, :upper_limit, :increment_percent, :slab, :sort_order)'
        );
        $stmt->execute([
            'tenant_id'         => $meta['tenant_id'],
            'firm_id'           => $firmId,
            'lower_limit'       => $lower,
            'upper_limit'       => $upper,
            'increment_percent' => $inc,
            'slab'              => $slabLabel,
            'sort_order'        => $sortOrder,
        ]);

        $id = (int) $pdo->lastInsertId();
        return Response::success([
            'id'                => $id,
            'firm_id'           => $firmId,
            'lower_limit'       => $lower,
            'upper_limit'       => $upper,
            'increment_percent' => $inc,
            'slab'              => $slabLabel,
            'sort_order'        => $sortOrder,
        ], 'Salary slab created.', 201);
    }

    public function salarySlabsUpdate(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $data = $request->all();

        $lower = isset($data['lower_limit']) && $data['lower_limit'] !== '' ? (float) $data['lower_limit'] : null;
        $upper = isset($data['upper_limit']) && $data['upper_limit'] !== '' ? (float) $data['upper_limit'] : null;
        $inc = isset($data['increment_percent']) && $data['increment_percent'] !== '' ? (float) $data['increment_percent'] : 0.0;

        if ($lower === null || $upper === null) {
            return Response::error('Lower Limit and Upper Limit are required.', 422);
        }
        if ($upper < $lower) {
            return Response::error('Upper Limit must be greater than or equal to Lower Limit.', 422);
        }

        $slabLabel = $this->formatSlabLabel($lower, $upper);
        $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== '' ? (int) $data['sort_order'] : 0;

        $stmt = $pdo->prepare(
            'UPDATE payroll_salary_slabs
             SET lower_limit = :lower, upper_limit = :upper, increment_percent = :inc,
                 slab = :slab, sort_order = :sort_order, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'lower'      => $lower,
            'upper'      => $upper,
            'inc'        => $inc,
            'slab'       => $slabLabel,
            'sort_order' => $sortOrder,
            'id'         => $id,
        ]);

        return Response::success([
            'id'                => $id,
            'lower_limit'       => $lower,
            'upper_limit'       => $upper,
            'increment_percent' => $inc,
            'slab'              => $slabLabel,
            'sort_order'        => $sortOrder,
        ], 'Salary slab updated.');
    }

    public function salarySlabsDestroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $pdo->prepare('UPDATE payroll_salary_slabs SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        return Response::success(null, 'Salary slab deleted.');
    }

    public function salarySlabsReorder(Request $request): Response
    {
        return $this->genericReorder($request, 'payroll_salary_slabs');
    }

    // =========================================================================
    // 2. ALLOWANCE & DEDUCTIONS
    // =========================================================================

    public function componentsIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $firmId = $this->resolveFirmId($request);
        $bindings = [];
        $where = 'WHERE deleted_at IS NULL';
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (firm_id = :firm_id OR firm_id IS NULL)';
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM payroll_components {$where} ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute($bindings);
        $raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $nameMap = [];
        foreach ($raw as $r) {
            $nameMap['comp_' . $r['id']] = $r['name'] . ' (' . $r['short_name'] . ')';
        }

        $rows = array_map(static function (array $r) use ($nameMap): array {
            $applyOn = (string) ($r['apply_on'] ?? 'basic');
            $applyLabel = $applyOn === 'basic' ? 'Basic' : ($nameMap[$applyOn] ?? $applyOn);
            return [
                'id'             => (int) $r['id'],
                'firm_id'        => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'name'           => (string) $r['name'],
                'short_name'     => (string) $r['short_name'],
                'nature'         => (string) $r['nature'],
                'type'           => (string) $r['type'],
                'value'          => (float) $r['value'],
                'apply_on'       => $applyOn,
                'apply_on_label' => $applyLabel,
                'calculate_on'   => (string) $r['calculate_on'],
                'sort_order'     => (int) $r['sort_order'],
                'is_active'      => (string) ($r['is_active'] ?? 'y'),
            ];
        }, $raw);

        return Response::success($rows);
    }

    public function componentsStore(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $data = $request->all();

        $name = trim((string) ($data['name'] ?? ''));
        $shortName = trim((string) ($data['short_name'] ?? ''));
        $nature = in_array($data['nature'] ?? '', ['allowance', 'deduction'], true) ? $data['nature'] : 'allowance';
        $type = in_array($data['type'] ?? '', ['percentage', 'fixed'], true) ? $data['type'] : 'fixed';
        $value = isset($data['value']) && $data['value'] !== '' ? (float) $data['value'] : 0.0;
        $applyOn = !empty($data['apply_on']) ? trim((string) $data['apply_on']) : 'basic';
        $calcOn = in_array($data['calculate_on'] ?? '', ['basic', 'working_days'], true) ? $data['calculate_on'] : 'basic';

        if ($name === '' || $shortName === '') {
            return Response::error('Name and Short Name are required.', 422);
        }

        $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== ''
            ? (int) $data['sort_order']
            : ((int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM payroll_components WHERE deleted_at IS NULL')->fetchColumn() + 1);

        $meta = $this->authMeta($context);
        $firmId = $this->resolveFirmId($request, $data) ?? $meta['firm_id'];

        $stmt = $pdo->prepare(
            'INSERT INTO payroll_components
                (tenant_id, firm_id, name, short_name, nature, type, value, apply_on, calculate_on, sort_order)
             VALUES
                (:tenant_id, :firm_id, :name, :short_name, :nature, :type, :value, :apply_on, :calculate_on, :sort_order)'
        );
        $stmt->execute([
            'tenant_id'    => $meta['tenant_id'],
            'firm_id'      => $firmId,
            'name'         => $name,
            'short_name'   => $shortName,
            'nature'       => $nature,
            'type'         => $type,
            'value'        => $value,
            'apply_on'     => $applyOn,
            'calculate_on' => $calcOn,
            'sort_order'   => $sortOrder,
        ]);

        return Response::success(['id' => (int) $pdo->lastInsertId()], 'Component created.', 201);
    }

    public function componentsUpdate(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $data = $request->all();

        $name = trim((string) ($data['name'] ?? ''));
        $shortName = trim((string) ($data['short_name'] ?? ''));
        $nature = in_array($data['nature'] ?? '', ['allowance', 'deduction'], true) ? $data['nature'] : 'allowance';
        $type = in_array($data['type'] ?? '', ['percentage', 'fixed'], true) ? $data['type'] : 'fixed';
        $value = isset($data['value']) && $data['value'] !== '' ? (float) $data['value'] : 0.0;
        $applyOn = !empty($data['apply_on']) ? trim((string) $data['apply_on']) : 'basic';
        if ($applyOn === 'comp_' . $id) {
            return Response::error('A component cannot apply on itself.', 422);
        }
        $calcOn = in_array($data['calculate_on'] ?? '', ['basic', 'working_days'], true) ? $data['calculate_on'] : 'basic';
        $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== '' ? (int) $data['sort_order'] : 0;

        if ($name === '' || $shortName === '') {
            return Response::error('Name and Short Name are required.', 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE payroll_components
             SET name = :name, short_name = :short_name, nature = :nature, type = :type,
                 value = :value, apply_on = :apply_on, calculate_on = :calculate_on,
                 sort_order = :sort_order, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'name'         => $name,
            'short_name'   => $shortName,
            'nature'       => $nature,
            'type'         => $type,
            'value'        => $value,
            'apply_on'     => $applyOn,
            'calculate_on' => $calcOn,
            'sort_order'   => $sortOrder,
            'id'           => $id,
        ]);

        return Response::success(['id' => $id], 'Component updated.');
    }

    public function componentsDestroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $pdo->prepare('UPDATE payroll_components SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        return Response::success(null, 'Component deleted.');
    }

    public function componentsReorder(Request $request): Response
    {
        return $this->genericReorder($request, 'payroll_components');
    }

    // =========================================================================
    // 3. PAYROLL SETTINGS (PF / ESI / TDS)
    // =========================================================================

    protected function fetchEffectiveSettings(\PDO $pdo, int $firmId): array
    {
        $defaults = [
            'firm_id'           => $firmId,
            'pf_pension_rate'   => 8.33,
            'pf_epf_rate'       => 3.67,
            'pf_employee_share' => 12.00,
            'pf_employer_share' => 12.00,
            'pf_max_amount'     => 15000.00,
            'esi_employee_rate' => 0.75,
            'esi_employer_rate' => 3.25,
            'esi_max_amount'    => 21000.00,
            'tds_percentage'    => 0.00,
        ];

        $stmt = $pdo->prepare(
            'SELECT * FROM payroll_settings WHERE firm_id IN (0, :fid) ORDER BY firm_id DESC LIMIT 1'
        );
        $stmt->execute(['fid' => $firmId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return $defaults;
        }

        $pension = (float) $row['pf_pension_rate'];
        $epf = (float) $row['pf_epf_rate'];

        return [
            'firm_id'           => $firmId,
            'pf_pension_rate'   => $pension,
            'pf_epf_rate'       => $epf,
            'pf_employee_share' => round($pension + $epf, 2),
            'pf_employer_share' => (float) $row['pf_employer_share'],
            'pf_max_amount'     => (float) $row['pf_max_amount'],
            'esi_employee_rate' => (float) $row['esi_employee_rate'],
            'esi_employer_rate' => (float) $row['esi_employer_rate'],
            'esi_max_amount'    => (float) $row['esi_max_amount'],
            'tds_percentage'    => (float) $row['tds_percentage'],
        ];
    }

    public function settingsIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $firmId = $this->resolveFirmId($request) ?? 0;
        return Response::success($this->fetchEffectiveSettings($pdo, $firmId));
    }

    public function settingsSave(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $data = $request->all();
        $meta = $this->authMeta($context);
        $firmId = $this->resolveFirmId($request, $data) ?? 0;

        $pension = max(0.0, (float) ($data['pf_pension_rate'] ?? 8.33));
        $epf = max(0.0, (float) ($data['pf_epf_rate'] ?? 3.67));
        $empShare = round($pension + $epf, 2); // Auto-calculated
        $emplrShare = max(0.0, (float) ($data['pf_employer_share'] ?? $empShare));
        $pfMax = max(0.0, (float) ($data['pf_max_amount'] ?? 15000.0));

        $esiEmp = max(0.0, (float) ($data['esi_employee_rate'] ?? 0.75));
        $esiEmplr = max(0.0, (float) ($data['esi_employer_rate'] ?? 3.25));
        $esiMax = max(0.0, (float) ($data['esi_max_amount'] ?? 21000.0));
        $tds = max(0.0, (float) ($data['tds_percentage'] ?? 0.0));

        $stmt = $pdo->prepare(
            'INSERT INTO payroll_settings
                (tenant_id, firm_id, pf_pension_rate, pf_epf_rate, pf_employee_share, pf_employer_share,
                 pf_max_amount, esi_employee_rate, esi_employer_rate, esi_max_amount, tds_percentage, updated_by)
             VALUES
                (:tenant_id, :firm_id, :pension, :epf, :emp_share, :emplr_share,
                 :pf_max, :esi_emp, :esi_emplr, :esi_max, :tds, :updated_by)
             ON DUPLICATE KEY UPDATE
                pf_pension_rate   = VALUES(pf_pension_rate),
                pf_epf_rate       = VALUES(pf_epf_rate),
                pf_employee_share = VALUES(pf_employee_share),
                pf_employer_share = VALUES(pf_employer_share),
                pf_max_amount     = VALUES(pf_max_amount),
                esi_employee_rate = VALUES(esi_employee_rate),
                esi_employer_rate = VALUES(esi_employer_rate),
                esi_max_amount    = VALUES(esi_max_amount),
                tds_percentage    = VALUES(tds_percentage),
                updated_by        = VALUES(updated_by),
                updated_at        = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id'   => $meta['tenant_id'],
            'firm_id'     => $firmId,
            'pension'     => $pension,
            'epf'         => $epf,
            'emp_share'   => $empShare,
            'emplr_share' => $emplrShare,
            'pf_max'      => $pfMax,
            'esi_emp'     => $esiEmp,
            'esi_emplr'   => $esiEmplr,
            'esi_max'     => $esiMax,
            'tds'         => $tds,
            'updated_by'  => $meta['user_id'],
        ]);

        return Response::success($this->fetchEffectiveSettings($pdo, $firmId), 'Payroll settings saved successfully.');
    }

    // =========================================================================
    // 4. PART PAYMENT
    // =========================================================================

    public function partPaymentsIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $bindings = [];
        $where = 'WHERE pp.deleted_at IS NULL';
        $firmId = $this->resolveFirmId($request);
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (pp.firm_id = :firm_id OR pp.firm_id IS NULL)';
        }
        $month = $request->query('salary_month');
        if ($month !== null && trim((string) $month) !== '') {
            $bindings['month'] = trim((string) $month);
            $where .= ' AND pp.salary_month = :month';
        }
        $empId = $request->query('employee_id');
        if ($empId !== null && $empId !== '') {
            $bindings['emp_id'] = (int) $empId;
            $where .= ' AND pp.employee_id = :emp_id';
        }

        $sql = "SELECT pp.*, u.first_name, u.middle_name, u.last_name, e.attendance_code, e.employee_type
                FROM payroll_part_payments pp
                INNER JOIN employees e ON e.id = pp.employee_id
                INNER JOIN users u ON u.id = e.user_id
                {$where}
                ORDER BY pp.paid_date DESC, pp.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        $rows = array_map(static function (array $r): array {
            $name = trim(implode(' ', array_filter([$r['first_name'] ?? '', $r['middle_name'] ?? '', $r['last_name'] ?? ''])));
            return [
                'id'              => (int) $r['id'],
                'firm_id'         => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'employee_id'     => (int) $r['employee_id'],
                'employee_name'   => $name,
                'attendance_code' => $r['attendance_code'] ?? '',
                'employee_type'   => $r['employee_type'] ?? '',
                'amount'          => (float) $r['amount'],
                'paid_date'       => (string) $r['paid_date'],
                'salary_month'    => (string) $r['salary_month'],
                'description'     => $r['description'] ?? '',
            ];
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

        return Response::success($rows);
    }

    public function partPaymentsStore(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $data = $request->all();
        $meta = $this->authMeta($context);

        $empId = (int) ($data['employee_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $paidDate = trim((string) ($data['paid_date'] ?? date('Y-m-d')));
        $salaryMonth = trim((string) ($data['salary_month'] ?? date('Y-m')));
        $desc = trim((string) ($data['description'] ?? ''));

        if ($empId <= 0 || $amount <= 0) {
            return Response::error('Employee and positive Amount are required.', 422);
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $salaryMonth)) {
            return Response::error('Salary Month must be in YYYY-MM format.', 422);
        }

        $firmId = $this->resolveFirmId($request, $data) ?? $meta['firm_id'];

        $stmt = $pdo->prepare(
            'INSERT INTO payroll_part_payments (tenant_id, firm_id, employee_id, amount, paid_date, salary_month, description, created_by)
             VALUES (:tenant_id, :firm_id, :employee_id, :amount, :paid_date, :salary_month, :description, :created_by)'
        );
        $stmt->execute([
            'tenant_id'    => $meta['tenant_id'],
            'firm_id'      => $firmId,
            'employee_id'  => $empId,
            'amount'       => $amount,
            'paid_date'    => $paidDate,
            'salary_month' => $salaryMonth,
            'description'  => $desc !== '' ? $desc : null,
            'created_by'   => $meta['user_id'],
        ]);

        return Response::success(['id' => (int) $pdo->lastInsertId()], 'Part payment recorded.', 201);
    }

    public function partPaymentsUpdate(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $data = $request->all();

        $empId = (int) ($data['employee_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $paidDate = trim((string) ($data['paid_date'] ?? date('Y-m-d')));
        $salaryMonth = trim((string) ($data['salary_month'] ?? date('Y-m')));
        $desc = trim((string) ($data['description'] ?? ''));

        if ($empId <= 0 || $amount <= 0) {
            return Response::error('Employee and positive Amount are required.', 422);
        }

        $pdo->prepare(
            'UPDATE payroll_part_payments
             SET employee_id = :emp_id, amount = :amount, paid_date = :paid_date,
                 salary_month = :salary_month, description = :desc, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([
            'emp_id'       => $empId,
            'amount'       => $amount,
            'paid_date'    => $paidDate,
            'salary_month' => $salaryMonth,
            'desc'         => $desc !== '' ? $desc : null,
            'id'           => $id,
        ]);

        return Response::success(['id' => $id], 'Part payment updated.');
    }

    public function partPaymentsDestroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $pdo->prepare('UPDATE payroll_part_payments SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        return Response::success(null, 'Part payment deleted.');
    }

    // =========================================================================
    // 5. LOANS (Auto EMI, Pay by Cash, View Transactions, Auto-deduct in Salary)
    // =========================================================================

    public function loansIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $bindings = [];
        $where = 'WHERE l.deleted_at IS NULL';
        $firmId = $this->resolveFirmId($request);
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (l.firm_id = :firm_id OR l.firm_id IS NULL)';
        }
        $empId = $request->query('employee_id');
        if ($empId !== null && $empId !== '') {
            $bindings['emp_id'] = (int) $empId;
            $where .= ' AND l.employee_id = :emp_id';
        }
        $status = $request->query('status');
        if ($status !== null && in_array($status, ['active', 'closed'], true)) {
            $bindings['status'] = $status;
            $where .= ' AND l.status = :status';
        }

        $sql = "SELECT l.*, u.first_name, u.middle_name, u.last_name, e.attendance_code, e.employee_type,
                       (SELECT COUNT(*) FROM payroll_loan_transactions t WHERE t.loan_id = l.id) AS transaction_count
                FROM payroll_loans l
                INNER JOIN employees e ON e.id = l.employee_id
                INNER JOIN users u ON u.id = e.user_id
                {$where}
                ORDER BY FIELD(l.status, 'active', 'closed'), l.paid_date DESC, l.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        $rows = array_map(static function (array $r): array {
            $name = trim(implode(' ', array_filter([$r['first_name'] ?? '', $r['middle_name'] ?? '', $r['last_name'] ?? ''])));
            return [
                'id'                => (int) $r['id'],
                'firm_id'           => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'employee_id'       => (int) $r['employee_id'],
                'employee_name'     => $name,
                'attendance_code'   => $r['attendance_code'] ?? '',
                'employee_type'     => $r['employee_type'] ?? '',
                'loan_amount'       => (float) $r['loan_amount'],
                'paid_date'         => (string) $r['paid_date'],
                'slab'              => (int) $r['slab'],
                'emi_amount'        => (float) $r['emi_amount'],
                'paid_emi'          => (int) $r['paid_emi'],
                'remaining_emi'     => (int) $r['remaining_emi'],
                'paid_amount'       => (float) $r['paid_amount'],
                'remaining_amount'  => (float) $r['remaining_amount'],
                'status'            => (string) $r['status'],
                'description'       => $r['description'] ?? '',
                'transaction_count' => (int) ($r['transaction_count'] ?? 0),
            ];
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

        return Response::success($rows);
    }

    public function loansStore(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $data = $request->all();
        $meta = $this->authMeta($context);

        $empId = (int) ($data['employee_id'] ?? 0);
        $loanAmount = (float) ($data['loan_amount'] ?? 0);
        $paidDate = trim((string) ($data['paid_date'] ?? date('Y-m-d')));
        $slab = max(1, (int) ($data['slab'] ?? 1));
        $emiAmount = round($loanAmount / $slab, 2);
        $paidEmi = max(0, min($slab, (int) ($data['paid_emi'] ?? 0)));
        $remainingEmi = max(0, $slab - $paidEmi);
        $paidAmount = min($loanAmount, round($paidEmi * $emiAmount, 2));
        $remainingAmount = max(0.0, round($loanAmount - $paidAmount, 2));
        $status = ($remainingEmi === 0 || $remainingAmount <= 0.0) ? 'closed' : 'active';
        $desc = trim((string) ($data['description'] ?? ''));

        if ($empId <= 0 || $loanAmount <= 0) {
            return Response::error('Employee and positive Loan Amount are required.', 422);
        }

        $firmId = $this->resolveFirmId($request, $data) ?? $meta['firm_id'];

        $stmt = $pdo->prepare(
            'INSERT INTO payroll_loans
                (tenant_id, firm_id, employee_id, loan_amount, paid_date, slab, emi_amount,
                 paid_emi, remaining_emi, paid_amount, remaining_amount, status, description, created_by)
             VALUES
                (:tenant_id, :firm_id, :employee_id, :loan_amount, :paid_date, :slab, :emi_amount,
                 :paid_emi, :remaining_emi, :paid_amount, :remaining_amount, :status, :description, :created_by)'
        );
        $stmt->execute([
            'tenant_id'        => $meta['tenant_id'],
            'firm_id'          => $firmId,
            'employee_id'      => $empId,
            'loan_amount'      => $loanAmount,
            'paid_date'        => $paidDate,
            'slab'             => $slab,
            'emi_amount'       => $emiAmount,
            'paid_emi'         => $paidEmi,
            'remaining_emi'    => $remainingEmi,
            'paid_amount'      => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status'           => $status,
            'description'      => $desc !== '' ? $desc : null,
            'created_by'       => $meta['user_id'],
        ]);

        return Response::success(['id' => (int) $pdo->lastInsertId()], 'Loan created successfully.', 201);
    }

    public function loansUpdate(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $data = $request->all();

        $loanStmt = $pdo->prepare('SELECT * FROM payroll_loans WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $loanStmt->execute(['id' => $id]);
        $existing = $loanStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$existing) {
            return Response::error('Loan not found.', 404);
        }

        $loanAmount = isset($data['loan_amount']) ? (float) $data['loan_amount'] : (float) $existing['loan_amount'];
        $slab = isset($data['slab']) ? max(1, (int) $data['slab']) : (int) $existing['slab'];
        $paidDate = trim((string) ($data['paid_date'] ?? $existing['paid_date']));
        $desc = trim((string) ($data['description'] ?? ''));

        $emiAmount = round($loanAmount / $slab, 2);
        $paidEmi = (int) $existing['paid_emi'];
        $paidAmount = (float) $existing['paid_amount'];
        $remainingEmi = max(0, $slab - $paidEmi);
        $remainingAmount = max(0.0, round($loanAmount - $paidAmount, 2));
        $status = ($remainingEmi === 0 || $remainingAmount <= 0.0) ? 'closed' : 'active';

        $pdo->prepare(
            'UPDATE payroll_loans
             SET loan_amount = :loan_amount, paid_date = :paid_date, slab = :slab,
                 emi_amount = :emi_amount, remaining_emi = :remaining_emi,
                 remaining_amount = :remaining_amount, status = :status,
                 description = :description, updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'loan_amount'      => $loanAmount,
            'paid_date'        => $paidDate,
            'slab'             => $slab,
            'emi_amount'       => $emiAmount,
            'remaining_emi'    => $remainingEmi,
            'remaining_amount' => $remainingAmount,
            'status'           => $status,
            'description'      => $desc !== '' ? $desc : null,
            'id'               => $id,
        ]);

        return Response::success(['id' => $id], 'Loan updated.');
    }

    public function loansDestroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $pdo->prepare('UPDATE payroll_loans SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        return Response::success(null, 'Loan deleted.');
    }

    /**
     * POST /api/payroll/loans/{id}/pay-cash
     * Records a cash repayment for a loan and updates Paid EMI / Remaining EMI.
     */
    public function loansPayCash(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $data = $request->all();
        $meta = $this->authMeta($context);

        $stmt = $pdo->prepare('SELECT * FROM payroll_loans WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $loan = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$loan) {
            return Response::error('Loan not found.', 404);
        }
        if ($loan['status'] === 'closed' || (float) $loan['remaining_amount'] <= 0.0) {
            return Response::error('This loan is already fully paid and closed.', 422);
        }

        $emiCount = max(1, (int) ($data['emi_count'] ?? 1));
        $emiCount = min($emiCount, max(1, (int) $loan['remaining_emi']));
        $defaultAmt = min((float) $loan['remaining_amount'], round((float) $loan['emi_amount'] * $emiCount, 2));
        $amount = isset($data['amount']) && $data['amount'] !== '' ? (float) $data['amount'] : $defaultAmt;
        if ($amount <= 0) {
            return Response::error('Payment amount must be greater than zero.', 422);
        }
        $amount = min($amount, (float) $loan['remaining_amount']);

        $paymentDate = trim((string) ($data['payment_date'] ?? date('Y-m-d')));
        $remarks = trim((string) ($data['remarks'] ?? 'Paid by Cash'));

        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                'INSERT INTO payroll_loan_transactions
                    (loan_id, employee_id, payment_mode, emi_count, amount, payment_date, remarks, created_by)
                 VALUES
                    (:loan_id, :emp_id, "cash", :emi_count, :amount, :payment_date, :remarks, :created_by)'
            )->execute([
                'loan_id'      => $id,
                'emp_id'       => (int) $loan['employee_id'],
                'emi_count'    => $emiCount,
                'amount'       => $amount,
                'payment_date' => $paymentDate,
                'remarks'      => $remarks !== '' ? $remarks : 'Paid by Cash',
                'created_by'   => $meta['user_id'],
            ]);

            $newPaidAmount = round((float) $loan['paid_amount'] + $amount, 2);
            $newRemainingAmount = max(0.0, round((float) $loan['loan_amount'] - $newPaidAmount, 2));
            $newPaidEmi = min((int) $loan['slab'], (int) $loan['paid_emi'] + $emiCount);
            if ($newRemainingAmount <= 0.01) {
                $newRemainingAmount = 0.0;
                $newPaidEmi = (int) $loan['slab'];
            }
            $newRemainingEmi = max(0, (int) $loan['slab'] - $newPaidEmi);
            $newStatus = ($newRemainingAmount <= 0.0 || $newRemainingEmi === 0) ? 'closed' : 'active';

            $pdo->prepare(
                'UPDATE payroll_loans
                 SET paid_emi = :paid_emi, remaining_emi = :rem_emi,
                     paid_amount = :paid_amt, remaining_amount = :rem_amt,
                     status = :status, updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'paid_emi' => $newPaidEmi,
                'rem_emi'  => $newRemainingEmi,
                'paid_amt' => $newPaidAmount,
                'rem_amt'  => $newRemainingAmount,
                'status'   => $newStatus,
                'id'       => $id,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Cash payment failed: ' . $e->getMessage(), 500);
        }

        return Response::success([
            'loan_id'          => $id,
            'paid_emi'         => $newPaidEmi,
            'remaining_emi'    => $newRemainingEmi,
            'paid_amount'      => $newPaidAmount,
            'remaining_amount' => $newRemainingAmount,
            'status'           => $newStatus,
        ], 'Cash EMI payment recorded successfully.');
    }

    /**
     * GET /api/payroll/loans/{id}/transactions
     */
    public function loansTransactions(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);

        $loanStmt = $pdo->prepare(
            "SELECT l.*, u.first_name, u.middle_name, u.last_name, e.attendance_code
             FROM payroll_loans l
             INNER JOIN employees e ON e.id = l.employee_id
             INNER JOIN users u ON u.id = e.user_id
             WHERE l.id = :id LIMIT 1"
        );
        $loanStmt->execute(['id' => $id]);
        $loan = $loanStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$loan) {
            return Response::error('Loan not found.', 404);
        }

        $txStmt = $pdo->prepare(
            'SELECT * FROM payroll_loan_transactions WHERE loan_id = :id ORDER BY payment_date DESC, id DESC'
        );
        $txStmt->execute(['id' => $id]);
        $transactions = array_map(static function (array $t): array {
            return [
                'id'           => (int) $t['id'],
                'loan_id'      => (int) $t['loan_id'],
                'payment_mode' => (string) $t['payment_mode'],
                'emi_count'    => (int) $t['emi_count'],
                'amount'       => (float) $t['amount'],
                'payment_date' => (string) $t['payment_date'],
                'salary_month' => $t['salary_month'],
                'payslip_id'   => $t['payslip_id'] !== null ? (int) $t['payslip_id'] : null,
                'remarks'      => $t['remarks'] ?? '',
                'created_at'   => $t['created_at'],
            ];
        }, $txStmt->fetchAll(\PDO::FETCH_ASSOC));

        $empName = trim(implode(' ', array_filter([$loan['first_name'] ?? '', $loan['middle_name'] ?? '', $loan['last_name'] ?? ''])));

        return Response::success([
            'loan'         => [
                'id'               => (int) $loan['id'],
                'employee_id'      => (int) $loan['employee_id'],
                'employee_name'    => $empName,
                'attendance_code'  => $loan['attendance_code'] ?? '',
                'loan_amount'      => (float) $loan['loan_amount'],
                'slab'             => (int) $loan['slab'],
                'emi_amount'       => (float) $loan['emi_amount'],
                'paid_emi'         => (int) $loan['paid_emi'],
                'remaining_emi'    => (int) $loan['remaining_emi'],
                'paid_amount'      => (float) $loan['paid_amount'],
                'remaining_amount' => (float) $loan['remaining_amount'],
                'status'           => (string) $loan['status'],
                'paid_date'        => (string) $loan['paid_date'],
                'description'      => $loan['description'] ?? '',
            ],
            'transactions' => $transactions,
        ]);
    }

    // =========================================================================
    // 6. GENERATE & VIEW PAY SLIPS
    // =========================================================================

    /**
     * GET /api/payroll/employees-for-payslip?firm_id=...&salary_month=YYYY-MM
     */
    public function employeesForPayslip(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        AttendanceController::ensureTables($pdo);

        $firmId = $this->resolveFirmId($request);
        $salaryMonth = trim((string) ($request->query('salary_month') ?: date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $salaryMonth)) {
            return Response::error('salary_month must be in YYYY-MM format.', 422);
        }

        $monthStart = $salaryMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $totalDaysInMonth = (int) date('t', strtotime($monthStart));

        $bindings = [];
        $where = "WHERE u.deleted_at IS NULL AND e.deleted_at IS NULL AND u.is_active = 'y'";
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }

        $sql = "SELECT e.id AS employee_id, u.id AS user_id, u.first_name, u.middle_name, u.last_name,
                       u.mobile_no, u.email, u.firm_id, e.employee_type, e.attendance_code, e.photo,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                       COALESCE(pep.basic_salary, 0) AS basic_salary,
                       COALESCE(pep.apply_pf, 'y') AS apply_pf,
                       COALESCE(pep.apply_esi, 'n') AS apply_esi,
                       COALESCE(pep.apply_tds, 'n') AS apply_tds
                FROM employees e
                INNER JOIN users u ON u.id = e.user_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                LEFT JOIN payroll_employee_profiles pep ON pep.employee_id = e.id
                {$where}
                GROUP BY e.id, u.id, pep.id
                ORDER BY u.first_name ASC, u.last_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $empRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $empIds = array_map(static fn($r) => (int) $r['employee_id'], $empRows);

        // Attendance stats for month
        $attMap = [];
        $ppMap = [];
        $loanMap = [];
        $existingSlipMap = [];

        if (!empty($empIds)) {
            $in = implode(',', array_fill(0, count($empIds), '?'));

            // Attendance
            $attStmt = $pdo->prepare(
                "SELECT ea.employee_id,
                        SUM(CASE WHEN al.treat_as = 'present' THEN 1 ELSE 0 END) AS present_cnt,
                        SUM(CASE WHEN al.treat_as = 'absent' THEN 1 ELSE 0 END) AS absent_cnt
                 FROM employee_attendances ea
                 INNER JOIN attendance_legends al ON al.id = ea.attendance_legend_id
                 WHERE ea.employee_id IN ({$in})
                   AND ea.attendance_date BETWEEN ? AND ?
                   AND ea.deleted_at IS NULL
                 GROUP BY ea.employee_id"
            );
            $attStmt->execute(array_merge($empIds, [$monthStart, $monthEnd]));
            foreach ($attStmt->fetchAll(\PDO::FETCH_ASSOC) as $a) {
                $attMap[(int) $a['employee_id']] = [
                    'present' => (int) $a['present_cnt'],
                    'absent'  => (int) $a['absent_cnt'],
                ];
            }

            // Part payments for this month
            $ppStmt = $pdo->prepare(
                "SELECT employee_id, SUM(amount) AS total_pp
                 FROM payroll_part_payments
                 WHERE employee_id IN ({$in}) AND salary_month = ? AND deleted_at IS NULL
                 GROUP BY employee_id"
            );
            $ppStmt->execute(array_merge($empIds, [$salaryMonth]));
            foreach ($ppStmt->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                $ppMap[(int) $p['employee_id']] = (float) $p['total_pp'];
            }

            // Active due loans (or already deducted for this month)
            $lStmt = $pdo->prepare(
                "SELECT l.id, l.employee_id, l.emi_amount, l.remaining_amount, l.remaining_emi, l.status, l.paid_date,
                        (SELECT SUM(t.amount) FROM payroll_loan_transactions t
                         WHERE t.loan_id = l.id AND t.payment_mode = 'salary_deduction' AND t.salary_month = ?) AS deducted_this_month
                 FROM payroll_loans l
                 WHERE l.employee_id IN ({$in}) AND l.deleted_at IS NULL AND l.paid_date <= ?"
            );
            $lStmt->execute(array_merge([$salaryMonth], $empIds, [$monthEnd]));
            foreach ($lStmt->fetchAll(\PDO::FETCH_ASSOC) as $l) {
                $eid = (int) $l['employee_id'];
                $deducted = (float) ($l['deducted_this_month'] ?? 0);
                if ($deducted > 0) {
                    $loanMap[$eid] = ($loanMap[$eid] ?? 0.0) + $deducted;
                } elseif ($l['status'] === 'active' && (int) $l['remaining_emi'] > 0 && (float) $l['remaining_amount'] > 0) {
                    $due = min((float) $l['emi_amount'], (float) $l['remaining_amount']);
                    $loanMap[$eid] = ($loanMap[$eid] ?? 0.0) + $due;
                }
            }

            // Existing generated payslips for this month
            $psStmt = $pdo->prepare(
                "SELECT id, employee_id, net_salary, gross_salary, total_deductions, status, generated_at
                 FROM payroll_payslips
                 WHERE employee_id IN ({$in}) AND salary_month = ?"
            );
            $psStmt->execute(array_merge($empIds, [$salaryMonth]));
            foreach ($psStmt->fetchAll(\PDO::FETCH_ASSOC) as $ps) {
                $existingSlipMap[(int) $ps['employee_id']] = [
                    'id'               => (int) $ps['id'],
                    'gross_salary'     => (float) $ps['gross_salary'],
                    'total_deductions' => (float) $ps['total_deductions'],
                    'net_salary'       => (float) $ps['net_salary'],
                    'status'           => (string) $ps['status'],
                    'generated_at'     => $ps['generated_at'],
                ];
            }
        }

        $items = array_map(static function (array $r) use ($attMap, $ppMap, $loanMap, $existingSlipMap, $totalDaysInMonth): array {
            $eid = (int) $r['employee_id'];
            $name = trim(implode(' ', array_filter([$r['first_name'] ?? '', $r['middle_name'] ?? '', $r['last_name'] ?? ''])));
            $att = $attMap[$eid] ?? null;
            $absentDays = $att ? $att['absent'] : 0;
            $presentDays = max(0, $totalDaysInMonth - $absentDays);

            return [
                'employee_id'     => $eid,
                'user_id'         => (int) $r['user_id'],
                'employee_name'   => $name,
                'employee_type'   => (string) ($r['employee_type'] ?? 'Teaching'),
                'attendance_code' => $r['attendance_code'] ?? '',
                'role_names'      => $r['role_names'] ?? '',
                'mobile_no'       => $r['mobile_no'] ?? '',
                'photo'           => $r['photo'] ?: null,
                'basic_salary'    => (float) $r['basic_salary'],
                'apply_pf'        => (string) $r['apply_pf'],
                'apply_esi'       => (string) $r['apply_esi'],
                'apply_tds'       => (string) $r['apply_tds'],
                'total_days'      => $totalDaysInMonth,
                'present_days'    => $presentDays,
                'absent_days'     => $absentDays,
                'marked_present'  => $att ? $att['present'] : 0,
                'part_payment'    => round($ppMap[$eid] ?? 0.0, 2),
                'loan_emi_due'    => round($loanMap[$eid] ?? 0.0, 2),
                'payslip'         => $existingSlipMap[$eid] ?? null,
            ];
        }, $empRows);

        return Response::success([
            'firm_id'      => $firmId,
            'salary_month' => $salaryMonth,
            'total_days'   => $totalDaysInMonth,
            'employees'    => $items,
        ]);
    }

    /**
     * POST /api/payroll/payslips/generate
     * Body: {
     *   firm_id?: number,
     *   salary_month: "YYYY-MM",
     *   employee_ids: number[],
     *   salary_overrides?: { [employee_id]: { basic_salary, apply_pf, apply_esi, apply_tds } }
     * }
     */
    public function generatePayslips(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        AttendanceController::ensureTables($pdo);

        $data = $request->all();
        $meta = $this->authMeta($context);
        $firmId = $this->resolveFirmId($request, $data) ?? $meta['firm_id'] ?? 0;
        $salaryMonth = trim((string) ($data['salary_month'] ?? date('Y-m')));
        $empIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['employee_ids'] ?? [])))));
        $overrides = is_array($data['salary_overrides'] ?? null) ? $data['salary_overrides'] : [];

        if (!preg_match('/^\d{4}-\d{2}$/', $salaryMonth)) {
            return Response::error('Valid salary_month (YYYY-MM) is required.', 422);
        }
        if (empty($empIds)) {
            return Response::error('Please select at least one employee to generate pay slips.', 422);
        }

        $monthStart = $salaryMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $totalDaysInMonth = (int) date('t', strtotime($monthStart));

        // 1. Load effective Payroll Settings (PF/ESI/TDS)
        $settings = $this->fetchEffectiveSettings($pdo, (int) $firmId);

        // 2. Load active Allowances & Deductions in sort_order
        $compWhere = 'WHERE deleted_at IS NULL AND is_active = "y"';
        $compBindings = [];
        if ($firmId > 0) {
            $compWhere .= ' AND (firm_id = :firm_id OR firm_id IS NULL)';
            $compBindings['firm_id'] = (int) $firmId;
        }
        $compStmt = $pdo->prepare("SELECT * FROM payroll_components {$compWhere} ORDER BY sort_order ASC, id ASC");
        $compStmt->execute($compBindings);
        $components = $compStmt->fetchAll(\PDO::FETCH_ASSOC);

        $generatedIds = [];

        try {
            $pdo->beginTransaction();

            $profileUpsert = $pdo->prepare(
                'INSERT INTO payroll_employee_profiles (employee_id, firm_id, basic_salary, apply_pf, apply_esi, apply_tds)
                 VALUES (:emp_id, :firm_id, :basic, :pf, :esi, :tds)
                 ON DUPLICATE KEY UPDATE
                   basic_salary = VALUES(basic_salary),
                   apply_pf     = VALUES(apply_pf),
                   apply_esi    = VALUES(apply_esi),
                   apply_tds    = VALUES(apply_tds),
                   updated_at   = NOW()'
            );

            foreach ($empIds as $empId) {
                // Save salary override if supplied from UI
                if (isset($overrides[$empId]) || isset($overrides[(string) $empId])) {
                    $ov = $overrides[$empId] ?? $overrides[(string) $empId];
                    $profileUpsert->execute([
                        'emp_id'  => $empId,
                        'firm_id' => $firmId > 0 ? $firmId : null,
                        'basic'   => max(0.0, (float) ($ov['basic_salary'] ?? 0)),
                        'pf'      => ($ov['apply_pf'] ?? 'y') === 'y' ? 'y' : 'n',
                        'esi'     => ($ov['apply_esi'] ?? 'n') === 'y' ? 'y' : 'n',
                        'tds'     => ($ov['apply_tds'] ?? 'n') === 'y' ? 'y' : 'n',
                    ]);
                }

                // Fetch employee profile
                $pStmt = $pdo->prepare('SELECT * FROM payroll_employee_profiles WHERE employee_id = :eid LIMIT 1');
                $pStmt->execute(['eid' => $empId]);
                $profile = $pStmt->fetch(\PDO::FETCH_ASSOC);

                $basicSalary = $profile ? (float) $profile['basic_salary'] : 0.0;
                $applyPf = $profile ? ($profile['apply_pf'] === 'y') : true;
                $applyEsi = $profile ? ($profile['apply_esi'] === 'y') : false;
                $applyTds = $profile ? ($profile['apply_tds'] === 'y') : false;

                // Attendance for month
                $attStmt = $pdo->prepare(
                    "SELECT SUM(CASE WHEN al.treat_as = 'present' THEN 1 ELSE 0 END) AS p_cnt,
                            SUM(CASE WHEN al.treat_as = 'absent' THEN 1 ELSE 0 END) AS a_cnt
                     FROM employee_attendances ea
                     INNER JOIN attendance_legends al ON al.id = ea.attendance_legend_id
                     WHERE ea.employee_id = :eid AND ea.attendance_date BETWEEN :s AND :e AND ea.deleted_at IS NULL"
                );
                $attStmt->execute(['eid' => $empId, 's' => $monthStart, 'e' => $monthEnd]);
                $attRow = $attStmt->fetch(\PDO::FETCH_ASSOC);
                $absentDays = (float) ($attRow['a_cnt'] ?? 0);
                $presentDays = max(0.0, $totalDaysInMonth - $absentDays);
                $ratio = $totalDaysInMonth > 0 ? ($presentDays / $totalDaysInMonth) : 1.0;

                $earnedBasic = round($basicSalary * $ratio, 2);

                // Compute Allowances & Deductions in sort_order
                $computedValues = [
                    'basic'        => $basicSalary,
                    'earned_basic' => $earnedBasic,
                ];
                $allowancesBreakdown = [];
                $deductionsBreakdown = [];
                $totalAllowances = 0.0;
                $totalCompDeductions = 0.0;

                foreach ($components as $comp) {
                    $cId = (int) $comp['id'];
                    $cNature = (string) $comp['nature'];
                    $cType = (string) $comp['type'];
                    $cVal = (float) $comp['value'];
                    $cApplyOn = (string) ($comp['apply_on'] ?: 'basic');
                    $cCalcOn = (string) ($comp['calculate_on'] ?: 'basic');

                    if ($cType === 'fixed') {
                        $amt = $cCalcOn === 'working_days' ? round($cVal * $ratio, 2) : round($cVal, 2);
                    } else {
                        if ($cApplyOn === 'basic') {
                            $base = $cCalcOn === 'working_days' ? $earnedBasic : $basicSalary;
                        } else {
                            $base = $computedValues[$cApplyOn] ?? ($cCalcOn === 'working_days' ? $earnedBasic : $basicSalary);
                        }
                        $amt = round($base * ($cVal / 100.0), 2);
                    }

                    $computedValues['comp_' . $cId] = $amt;

                    $item = [
                        'id'           => $cId,
                        'name'         => (string) $comp['name'],
                        'short_name'   => (string) $comp['short_name'],
                        'type'         => $cType,
                        'value'        => $cVal,
                        'calculate_on' => $cCalcOn,
                        'amount'       => $amt,
                    ];

                    if ($cNature === 'allowance') {
                        $allowancesBreakdown[] = $item;
                        $totalAllowances += $amt;
                    } else {
                        $deductionsBreakdown[] = $item;
                        $totalCompDeductions += $amt;
                    }
                }

                $totalAllowances = round($totalAllowances, 2);
                $totalCompDeductions = round($totalCompDeductions, 2);
                $grossSalary = round($earnedBasic + $totalAllowances, 2);

                // Statutory: PF
                $pfEmployee = 0.0;
                $pfEmployer = 0.0;
                $pfPension = 0.0;
                $pfEpf = 0.0;
                if ($applyPf && $earnedBasic > 0) {
                    $pfCap = (float) $settings['pf_max_amount'];
                    $pfWage = ($pfCap > 0) ? min($earnedBasic, $pfCap) : $earnedBasic;
                    $pfPension = round($pfWage * ((float) $settings['pf_pension_rate'] / 100.0), 2);
                    $pfEpf = round($pfWage * ((float) $settings['pf_epf_rate'] / 100.0), 2);
                    $pfEmployee = round($pfWage * ((float) $settings['pf_employee_share'] / 100.0), 2);
                    $pfEmployer = round($pfWage * ((float) $settings['pf_employer_share'] / 100.0), 2);
                }

                // Statutory: ESI
                $esiEmployee = 0.0;
                $esiEmployer = 0.0;
                if ($applyEsi && $grossSalary > 0) {
                    $esiCap = (float) $settings['esi_max_amount'];
                    if ($esiCap <= 0 || $grossSalary <= $esiCap) {
                        $esiEmployee = round($grossSalary * ((float) $settings['esi_employee_rate'] / 100.0), 2);
                        $esiEmployer = round($grossSalary * ((float) $settings['esi_employer_rate'] / 100.0), 2);
                    }
                }

                // Statutory: TDS
                $tdsAmount = 0.0;
                if ($applyTds && $grossSalary > 0 && (float) $settings['tds_percentage'] > 0) {
                    $tdsAmount = round($grossSalary * ((float) $settings['tds_percentage'] / 100.0), 2);
                }

                // Part Payment Deduction for this salary month
                $ppStmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(amount), 0) FROM payroll_part_payments
                     WHERE employee_id = :eid AND salary_month = :month AND deleted_at IS NULL'
                );
                $ppStmt->execute(['eid' => $empId, 'month' => $salaryMonth]);
                $partPaymentDed = round((float) $ppStmt->fetchColumn(), 2);

                // Loan EMI Auto-Deduction (avoid double deducting same month on regenerate)
                $loanDed = 0.0;
                $newLoanTxToInsert = [];
                $lStmt = $pdo->prepare(
                    'SELECT * FROM payroll_loans
                     WHERE employee_id = :eid AND deleted_at IS NULL AND paid_date <= :month_end
                     ORDER BY paid_date ASC, id ASC'
                );
                $lStmt->execute(['eid' => $empId, 'month_end' => $monthEnd]);
                foreach ($lStmt->fetchAll(\PDO::FETCH_ASSOC) as $loan) {
                    $lid = (int) $loan['id'];
                    // Check if already deducted for this month
                    $chkTx = $pdo->prepare(
                        'SELECT COALESCE(SUM(amount), 0) FROM payroll_loan_transactions
                         WHERE loan_id = :lid AND payment_mode = "salary_deduction" AND salary_month = :month'
                    );
                    $chkTx->execute(['lid' => $lid, 'month' => $salaryMonth]);
                    $alreadyDed = (float) $chkTx->fetchColumn();

                    if ($alreadyDed > 0) {
                        $loanDed += $alreadyDed;
                    } elseif ($loan['status'] === 'active' && (int) $loan['remaining_emi'] > 0 && (float) $loan['remaining_amount'] > 0) {
                        $dedAmt = min((float) $loan['emi_amount'], (float) $loan['remaining_amount']);
                        $loanDed += $dedAmt;
                        $newLoanTxToInsert[] = ['loan' => $loan, 'amount' => $dedAmt];
                    }
                }
                $loanDed = round($loanDed, 2);

                $totalDeductions = round(
                    $totalCompDeductions + $pfEmployee + $esiEmployee + $tdsAmount + $partPaymentDed + $loanDed,
                    2
                );
                $netSalary = max(0.0, round($grossSalary - $totalDeductions, 2));

                // Upsert payslip
                $psUpsert = $pdo->prepare(
                    'INSERT INTO payroll_payslips
                        (tenant_id, firm_id, employee_id, salary_month, total_days, working_days, present_days, absent_days,
                         basic_salary, earned_basic, allowances_json, deductions_json, total_allowances,
                         total_component_deductions, gross_salary, pf_employee, pf_employer, pf_pension, pf_epf,
                         esi_employee, esi_employer, tds_amount, part_payment_deduction, loan_deduction,
                         total_deductions, net_salary, status, generated_by)
                     VALUES
                        (:tenant_id, :firm_id, :emp_id, :month, :total_days, :working_days, :present_days, :absent_days,
                         :basic_salary, :earned_basic, :allow_json, :ded_json, :total_allow,
                         :total_comp_ded, :gross, :pf_emp, :pf_emplr, :pf_pen, :pf_epf,
                         :esi_emp, :esi_emplr, :tds, :pp_ded, :loan_ded,
                         :total_ded, :net, "generated", :gen_by)
                     ON DUPLICATE KEY UPDATE
                        firm_id                    = VALUES(firm_id),
                        total_days                 = VALUES(total_days),
                        working_days               = VALUES(working_days),
                        present_days               = VALUES(present_days),
                        absent_days                = VALUES(absent_days),
                        basic_salary               = VALUES(basic_salary),
                        earned_basic               = VALUES(earned_basic),
                        allowances_json            = VALUES(allowances_json),
                        deductions_json            = VALUES(deductions_json),
                        total_allowances           = VALUES(total_allowances),
                        total_component_deductions = VALUES(total_component_deductions),
                        gross_salary               = VALUES(gross_salary),
                        pf_employee                = VALUES(pf_employee),
                        pf_employer                = VALUES(pf_employer),
                        pf_pension                 = VALUES(pf_pension),
                        pf_epf                     = VALUES(pf_epf),
                        esi_employee               = VALUES(esi_employee),
                        esi_employer               = VALUES(esi_employer),
                        tds_amount                 = VALUES(tds_amount),
                        part_payment_deduction     = VALUES(part_payment_deduction),
                        loan_deduction             = VALUES(loan_deduction),
                        total_deductions           = VALUES(total_deductions),
                        net_salary                 = VALUES(net_salary),
                        generated_by               = VALUES(generated_by),
                        updated_at                 = CURRENT_TIMESTAMP'
                );

                $psUpsert->execute([
                    'tenant_id'      => $meta['tenant_id'],
                    'firm_id'        => $firmId > 0 ? $firmId : null,
                    'emp_id'         => $empId,
                    'month'          => $salaryMonth,
                    'total_days'     => $totalDaysInMonth,
                    'working_days'   => $totalDaysInMonth,
                    'present_days'   => $presentDays,
                    'absent_days'    => $absentDays,
                    'basic_salary'   => $basicSalary,
                    'earned_basic'   => $earnedBasic,
                    'allow_json'     => json_encode($allowancesBreakdown),
                    'ded_json'       => json_encode($deductionsBreakdown),
                    'total_allow'    => $totalAllowances,
                    'total_comp_ded' => $totalCompDeductions,
                    'gross'          => $grossSalary,
                    'pf_emp'         => $pfEmployee,
                    'pf_emplr'       => $pfEmployer,
                    'pf_pen'         => $pfPension,
                    'pf_epf'         => $pfEpf,
                    'esi_emp'        => $esiEmployee,
                    'esi_emplr'      => $esiEmployer,
                    'tds'            => $tdsAmount,
                    'pp_ded'         => $partPaymentDed,
                    'loan_ded'       => $loanDed,
                    'total_ded'      => $totalDeductions,
                    'net'            => $netSalary,
                    'gen_by'         => $meta['user_id'],
                ]);

                // Resolve payslip ID
                $idStmt = $pdo->prepare('SELECT id FROM payroll_payslips WHERE employee_id = :eid AND salary_month = :m LIMIT 1');
                $idStmt->execute(['eid' => $empId, 'm' => $salaryMonth]);
                $payslipId = (int) $idStmt->fetchColumn();
                $generatedIds[] = $payslipId;

                // Record auto loan deductions & update loan balances
                foreach ($newLoanTxToInsert as $entry) {
                    $loan = $entry['loan'];
                    $dedAmt = (float) $entry['amount'];
                    $lid = (int) $loan['id'];

                    $pdo->prepare(
                        'INSERT INTO payroll_loan_transactions
                            (loan_id, employee_id, payment_mode, emi_count, amount, payment_date, salary_month, payslip_id, remarks, created_by)
                         VALUES
                            (:lid, :eid, "salary_deduction", 1, :amt, :pdate, :month, :psid, :remarks, :cby)'
                    )->execute([
                        'lid'     => $lid,
                        'eid'     => $empId,
                        'amt'     => $dedAmt,
                        'pdate'   => date('Y-m-d'),
                        'month'   => $salaryMonth,
                        'psid'    => $payslipId,
                        'remarks' => "Auto-deducted in {$salaryMonth} Pay Slip",
                        'cby'     => $meta['user_id'],
                    ]);

                    $newPaidAmt = round((float) $loan['paid_amount'] + $dedAmt, 2);
                    $newRemAmt = max(0.0, round((float) $loan['loan_amount'] - $newPaidAmt, 2));
                    $newPaidEmi = min((int) $loan['slab'], (int) $loan['paid_emi'] + 1);
                    if ($newRemAmt <= 0.01) {
                        $newRemAmt = 0.0;
                        $newPaidEmi = (int) $loan['slab'];
                    }
                    $newRemEmi = max(0, (int) $loan['slab'] - $newPaidEmi);
                    $newStatus = ($newRemAmt <= 0.0 || $newRemEmi === 0) ? 'closed' : 'active';

                    $pdo->prepare(
                        'UPDATE payroll_loans
                         SET paid_emi = :pe, remaining_emi = :re, paid_amount = :pa, remaining_amount = :ra,
                             status = :st, updated_at = NOW()
                         WHERE id = :lid'
                    )->execute([
                        'pe'  => $newPaidEmi,
                        're'  => $newRemEmi,
                        'pa'  => $newPaidAmt,
                        'ra'  => $newRemAmt,
                        'st'  => $newStatus,
                        'lid' => $lid,
                    ]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to generate pay slips: ' . $e->getMessage(), 500);
        }

        // Return full hydrated payslips ready for instant view/print
        $slips = $this->hydratePayslips($pdo, $generatedIds);

        return Response::success([
            'generated_count' => count($slips),
            'payslips'        => $slips,
        ], 'Generated ' . count($slips) . ' pay slip(s) successfully.');
    }

    public function payslipsIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $bindings = [];
        $where = 'WHERE 1=1';
        $firmId = $this->resolveFirmId($request);
        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (ps.firm_id = :firm_id OR ps.firm_id IS NULL)';
        }
        $month = $request->query('salary_month');
        if ($month !== null && trim((string) $month) !== '') {
            $bindings['month'] = trim((string) $month);
            $where .= ' AND ps.salary_month = :month';
        }
        $empId = $request->query('employee_id');
        if ($empId !== null && $empId !== '') {
            $bindings['emp_id'] = (int) $empId;
            $where .= ' AND ps.employee_id = :emp_id';
        }

        $stmt = $pdo->prepare("SELECT ps.id FROM payroll_payslips ps {$where} ORDER BY ps.salary_month DESC, ps.id DESC LIMIT 500");
        $stmt->execute($bindings);
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        return Response::success($this->hydratePayslips($pdo, $ids));
    }

    public function payslipShow(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);
        $slips = $this->hydratePayslips($pdo, [$id]);
        if (empty($slips)) {
            return Response::error('Pay slip not found.', 404);
        }
        return Response::success($slips[0]);
    }

    public function payslipDestroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $id = (int) ($params['id'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Reverse any loan salary_deduction transactions linked to this payslip
            $txStmt = $pdo->prepare('SELECT * FROM payroll_loan_transactions WHERE payslip_id = :psid');
            $txStmt->execute(['psid' => $id]);
            foreach ($txStmt->fetchAll(\PDO::FETCH_ASSOC) as $tx) {
                $lid = (int) $tx['loan_id'];
                $amt = (float) $tx['amount'];
                $cnt = (int) $tx['emi_count'];
                $pdo->prepare(
                    'UPDATE payroll_loans
                     SET paid_emi = GREATEST(0, paid_emi - :cnt),
                         remaining_emi = LEAST(slab, remaining_emi + :cnt2),
                         paid_amount = GREATEST(0, paid_amount - :amt),
                         remaining_amount = LEAST(loan_amount, remaining_amount + :amt2),
                         status = "active",
                         updated_at = NOW()
                     WHERE id = :lid'
                )->execute([
                    'cnt'  => $cnt,
                    'cnt2' => $cnt,
                    'amt'  => $amt,
                    'amt2' => $amt,
                    'lid'  => $lid,
                ]);
            }
            $pdo->prepare('DELETE FROM payroll_loan_transactions WHERE payslip_id = :psid')->execute(['psid' => $id]);
            $pdo->prepare('DELETE FROM payroll_payslips WHERE id = :id')->execute(['id' => $id]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to delete pay slip: ' . $e->getMessage(), 500);
        }

        return Response::success(null, 'Pay slip deleted.');
    }

    /**
     * Hydrate payslips with full Firm & Employee info.
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    protected function hydratePayslips(\PDO $pdo, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ps.*,
                       u.first_name, u.middle_name, u.last_name, u.email, u.mobile_no, u.doj,
                       e.employee_type, e.attendance_code, e.photo,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                       f.name AS firm_name, f.address AS firm_address, f.email AS firm_email,
                       f.phone_no AS firm_phone, f.logo AS firm_logo
                FROM payroll_payslips ps
                INNER JOIN employees e ON e.id = ps.employee_id
                INNER JOIN users u ON u.id = e.user_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                LEFT JOIN firms f ON f.id = COALESCE(ps.firm_id, u.firm_id)
                WHERE ps.id IN ({$in})
                GROUP BY ps.id, e.id, u.id, f.id
                ORDER BY u.first_name ASC, ps.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $r): array {
            $name = trim(implode(' ', array_filter([$r['first_name'] ?? '', $r['middle_name'] ?? '', $r['last_name'] ?? ''])));
            return [
                'id'                         => (int) $r['id'],
                'firm_id'                    => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'firm'                       => [
                    'name'    => $r['firm_name'] ?: 'Institution Payroll',
                    'address' => $r['firm_address'] ?: '',
                    'email'   => $r['firm_email'] ?: '',
                    'phone'   => $r['firm_phone'] ?: '',
                    'logo'    => $r['firm_logo'] ?: null,
                ],
                'employee_id'                => (int) $r['employee_id'],
                'employee_name'              => $name,
                'employee_type'              => $r['employee_type'] ?? '',
                'attendance_code'            => $r['attendance_code'] ?? '',
                'role_names'                 => $r['role_names'] ?? '',
                'mobile_no'                  => $r['mobile_no'] ?? '',
                'email'                      => $r['email'] ?? '',
                'doj'                        => $r['doj'] ?? '',
                'photo'                      => $r['photo'] ?: null,
                'salary_month'               => (string) $r['salary_month'],
                'total_days'                 => (int) $r['total_days'],
                'working_days'               => (float) $r['working_days'],
                'present_days'               => (float) $r['present_days'],
                'absent_days'                => (float) $r['absent_days'],
                'basic_salary'               => (float) $r['basic_salary'],
                'earned_basic'               => (float) $r['earned_basic'],
                'allowances'                 => json_decode((string) ($r['allowances_json'] ?: '[]'), true) ?: [],
                'component_deductions'       => json_decode((string) ($r['deductions_json'] ?: '[]'), true) ?: [],
                'total_allowances'           => (float) $r['total_allowances'],
                'total_component_deductions' => (float) $r['total_component_deductions'],
                'gross_salary'               => (float) $r['gross_salary'],
                'pf_employee'                => (float) $r['pf_employee'],
                'pf_employer'                => (float) $r['pf_employer'],
                'pf_pension'                 => (float) $r['pf_pension'],
                'pf_epf'                     => (float) $r['pf_epf'],
                'esi_employee'               => (float) $r['esi_employee'],
                'esi_employer'               => (float) $r['esi_employer'],
                'tds_amount'                 => (float) $r['tds_amount'],
                'part_payment_deduction'     => (float) $r['part_payment_deduction'],
                'loan_deduction'             => (float) $r['loan_deduction'],
                'total_deductions'           => (float) $r['total_deductions'],
                'net_salary'                 => (float) $r['net_salary'],
                'status'                     => (string) $r['status'],
                'generated_at'               => $r['generated_at'],
            ];
        }, $rows);
    }

    // =========================================================================
    // 7. EMPLOYEE ALLOTMENTS (PF / ESI / TDS SEPARATE ALLOTMENT)
    // =========================================================================

    public function allotmentIndex(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $type = strtolower(trim((string) ($request->query('type') ?: 'pf')));
        if (!in_array($type, ['pf', 'esi', 'tds'], true)) {
            $type = 'pf';
        }

        $firmId = $this->resolveFirmId($request);
        $empType = $request->query('employee_type') ? trim((string) $request->query('employee_type')) : null;
        $roleId = $request->query('role_id') ? (int) $request->query('role_id') : null;
        $status = $request->query('status') ? trim((string) $request->query('status')) : 'all';
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;

        $targetCol = match ($type) {
            'esi' => 'apply_esi',
            'tds' => 'apply_tds',
            default => 'apply_pf',
        };

        $bindings = [];
        $where = "WHERE u.deleted_at IS NULL AND e.deleted_at IS NULL AND u.is_active = 'y'";

        if ($firmId !== null) {
            $bindings['firm_id'] = $firmId;
            $where .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }
        if ($empType) {
            $bindings['emp_type'] = $empType;
            $where .= ' AND e.employee_type = :emp_type';
        }
        if ($roleId) {
            $bindings['role_id'] = $roleId;
            $where .= ' AND EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = :role_id)';
        }
        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $bindings['q1'] = $term;
            $bindings['q2'] = $term;
            $bindings['q3'] = $term;
            $bindings['q4'] = $term;
            $bindings['q5'] = $term;
            $bindings['q6'] = $term;
            $where .= ' AND (u.first_name LIKE :q1 OR u.middle_name LIKE :q2 OR u.last_name LIKE :q3 OR e.attendance_code LIKE :q4 OR u.mobile_no LIKE :q5 OR pep.uan_no LIKE :q6 OR pep.pan_no LIKE :q6 OR pep.esi_no LIKE :q6)';
        }

        if ($status === 'allotted') {
            $defaultVal = $type === 'pf' ? 'y' : 'n';
            $where .= " AND COALESCE(pep.{$targetCol}, '{$defaultVal}') = 'y'";
        } elseif ($status === 'unallotted') {
            $defaultVal = $type === 'pf' ? 'y' : 'n';
            $where .= " AND COALESCE(pep.{$targetCol}, '{$defaultVal}') = 'n'";
        }

        $sql = "SELECT e.id AS employee_id, u.id AS user_id, u.first_name, u.middle_name, u.last_name,
                       u.email, u.mobile_no, u.firm_id, e.employee_type, e.attendance_code, e.photo,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                       COALESCE(pep.basic_salary, 0.00) AS basic_salary,
                       COALESCE(pep.apply_pf, 'y') AS apply_pf,
                       COALESCE(pep.apply_esi, 'n') AS apply_esi,
                       COALESCE(pep.apply_tds, 'n') AS apply_tds,
                       pep.uan_no,
                       pep.esi_no,
                       pep.pan_no,
                       pep.bank_name,
                       pep.account_no,
                       pep.ifsc_code
                FROM employees e
                INNER JOIN users u ON u.id = e.user_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                LEFT JOIN payroll_employee_profiles pep ON pep.employee_id = e.id
                {$where}
                GROUP BY e.id, u.id, pep.id
                ORDER BY u.first_name ASC, u.last_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $allottedCount = 0;
        $unallottedCount = 0;
        $totalWages = 0.0;

        $employees = [];
        foreach ($rows as $r) {
            $isAllotted = match ($type) {
                'esi' => ($r['apply_esi'] === 'y'),
                'tds' => ($r['apply_tds'] === 'y'),
                default => ($r['apply_pf'] === 'y'),
            };

            if ($isAllotted) {
                $allottedCount++;
                $totalWages += (float) $r['basic_salary'];
            } else {
                $unallottedCount++;
            }

            $name = trim(implode(' ', array_filter([$r['first_name'] ?? '', $r['middle_name'] ?? '', $r['last_name'] ?? ''])));

            $employees[] = [
                'employee_id'     => (int) $r['employee_id'],
                'user_id'         => (int) $r['user_id'],
                'employee_name'   => $name,
                'employee_type'   => (string) ($r['employee_type'] ?? 'Teaching'),
                'attendance_code' => $r['attendance_code'] ?? '',
                'role_names'      => $r['role_names'] ?? '',
                'mobile_no'       => $r['mobile_no'] ?? '',
                'email'           => $r['email'] ?? '',
                'firm_id'         => $r['firm_id'] !== null ? (int) $r['firm_id'] : null,
                'photo'           => $r['photo'] ?: null,
                'basic_salary'    => (float) $r['basic_salary'],
                'is_allotted'     => $isAllotted,
                'apply_pf'        => (string) $r['apply_pf'],
                'apply_esi'       => (string) $r['apply_esi'],
                'apply_tds'       => (string) $r['apply_tds'],
                'uan_no'          => $r['uan_no'] ?? '',
                'esi_no'          => $r['esi_no'] ?? '',
                'pan_no'          => $r['pan_no'] ?? '',
                'bank_name'       => $r['bank_name'] ?? '',
                'account_no'      => $r['account_no'] ?? '',
                'ifsc_code'       => $r['ifsc_code'] ?? '',
            ];
        }

        $effectiveSettings = $this->fetchEffectiveSettings($pdo, $firmId ?? 0);

        return Response::success([
            'type'        => $type,
            'firm_id'     => $firmId,
            'settings'    => $effectiveSettings,
            'stats'       => [
                'total'       => count($employees),
                'allotted'    => $allottedCount,
                'unallotted'  => $unallottedCount,
                'total_wages' => round($totalWages, 2),
            ],
            'employees'   => $employees,
        ]);
    }

    public function allotmentSave(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $data = $request->all();
        $type = strtolower(trim((string) ($data['type'] ?? 'pf')));
        if (!in_array($type, ['pf', 'esi', 'tds'], true)) {
            return Response::error('Invalid allotment type. Must be pf, esi, or tds.', 422);
        }

        $targetCol = match ($type) {
            'esi' => 'apply_esi',
            'tds' => 'apply_tds',
            default => 'apply_pf',
        };

        $firmId = $this->resolveFirmId($request, $data);

        // Check if bulk action format: { type, action: 'assign'|'unassign', employee_ids: [...] }
        if (isset($data['action']) && isset($data['employee_ids']) && is_array($data['employee_ids'])) {
            $flag = ($data['action'] === 'assign') ? 'y' : 'n';
            $ids = array_filter(array_map('intval', $data['employee_ids']));
            if (empty($ids)) {
                return Response::error('No employee IDs provided.', 422);
            }

            try {
                $pdo->beginTransaction();
                $upsert = $pdo->prepare(
                    "INSERT INTO payroll_employee_profiles (employee_id, firm_id, {$targetCol})
                     VALUES (:eid, :fid, :flag)
                     ON DUPLICATE KEY UPDATE {$targetCol} = VALUES({$targetCol}), updated_at = NOW()"
                );
                foreach ($ids as $eid) {
                    $upsert->execute([
                        'eid'  => $eid,
                        'fid'  => $firmId,
                        'flag' => $flag,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return Response::error('Failed to update allotment: ' . $e->getMessage(), 500);
            }

            return Response::success(['updated' => count($ids)], "Updated allotment for " . count($ids) . " employee(s).");
        }

        // Granular items format: { type, items: [ { employee_id, is_allotted, uan_no, esi_no, pan_no, basic_salary } ] }
        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            return Response::error('items array is required.', 422);
        }

        try {
            $pdo->beginTransaction();

            $upsertSql = "INSERT INTO payroll_employee_profiles
                            (employee_id, firm_id, {$targetCol}, uan_no, esi_no, pan_no, basic_salary)
                          VALUES
                            (:eid, :fid, :flag, :uan, :esi, :pan, :basic)
                          ON DUPLICATE KEY UPDATE
                            {$targetCol} = VALUES({$targetCol}),
                            uan_no       = COALESCE(VALUES(uan_no), uan_no),
                            esi_no       = COALESCE(VALUES(esi_no), esi_no),
                            pan_no       = COALESCE(VALUES(pan_no), pan_no),
                            basic_salary = CASE WHEN VALUES(basic_salary) > 0 THEN VALUES(basic_salary) ELSE basic_salary END,
                            updated_at   = NOW()";

            $stmt = $pdo->prepare($upsertSql);
            $updated = 0;

            foreach ($items as $item) {
                $eid = (int) ($item['employee_id'] ?? 0);
                if ($eid <= 0) continue;

                $isAllotted = !empty($item['is_allotted']);
                $flag = $isAllotted ? 'y' : 'n';
                $uan = isset($item['uan_no']) && trim((string) $item['uan_no']) !== '' ? trim((string) $item['uan_no']) : null;
                $esi = isset($item['esi_no']) && trim((string) $item['esi_no']) !== '' ? trim((string) $item['esi_no']) : null;
                $pan = isset($item['pan_no']) && trim((string) $item['pan_no']) !== '' ? strtoupper(trim((string) $item['pan_no'])) : null;
                $basic = isset($item['basic_salary']) && $item['basic_salary'] !== '' ? (float) $item['basic_salary'] : 0.0;

                $stmt->execute([
                    'eid'   => $eid,
                    'fid'   => $firmId,
                    'flag'  => $flag,
                    'uan'   => $uan,
                    'esi'   => $esi,
                    'pan'   => $pan,
                    'basic' => $basic,
                ]);
                $updated++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to save allotments: ' . $e->getMessage(), 500);
        }

        $typeUpper = strtoupper($type);
        return Response::success(['updated' => $updated], "Successfully saved {$typeUpper} allotments for {$updated} employee(s).");
    }

    /**
     * Reusable drag-and-drop reorder helper for Payroll tables.
     */
    protected function genericReorder(Request $request, string $table): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);
        $data = $request->all();

        $orderIds = $data['order'] ?? $data['ids'] ?? null;
        $items = $data['items'] ?? null;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE `{$table}` SET sort_order = :ord, updated_at = NOW() WHERE id = :id");

            if (is_array($orderIds) && !empty($orderIds)) {
                $pos = 1;
                foreach ($orderIds as $id) {
                    $stmt->execute(['ord' => $pos++, 'id' => (int) $id]);
                }
                $pdo->commit();
                return Response::success(['updated' => count($orderIds)], 'Order updated.');
            }

            if (is_array($items) && !empty($items)) {
                $cnt = 0;
                foreach ($items as $it) {
                    if (isset($it['id'], $it['sort_order'])) {
                        $stmt->execute(['ord' => (int) $it['sort_order'], 'id' => (int) $it['id']]);
                        $cnt++;
                    }
                }
                $pdo->commit();
                return Response::success(['updated' => $cnt], 'Order updated.');
            }

            $pdo->rollBack();
            return Response::error('Invalid reorder payload.', 422);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to reorder: ' . $e->getMessage(), 500);
        }
    }
}


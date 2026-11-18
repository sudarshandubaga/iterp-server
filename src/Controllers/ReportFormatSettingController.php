<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Throwable;

/**
 * Controller for centralized Report Format Settings.
 * Admins select the active template for Marksheet, Student ID Card,
 * Teacher ID Card, Admit Card, Fee Receipt, Certificate, etc.
 */
class ReportFormatSettingController
{
    /**
     * Master catalog of configurable report types and their allowed formats.
     */
    public const CATALOG = [
        'marksheet' => [
            'label'       => 'Student Marksheet',
            'category'    => 'Examination',
            'icon'        => 'ri-award-line',
            'report_path' => '/exam/marksheet',
            'default'     => 'madhuvan',
            'formats'     => [
                [
                    'key'         => 'madhuvan',
                    'name'        => 'Format 1 — School Register / Madhuvan',
                    'description' => 'Multi-exam session columns (M.M. / M.O. / Total), Co-Scholastic activities, Attendance & Remarks block.',
                    'badge'       => 'Multi-Exam Table',
                ],
                [
                    'key'         => 'cbse',
                    'name'        => 'Format 2 — CBSE Standard Report Card',
                    'description' => 'Part 1 Scholastic, Part 2 Co-Scholastic 3-point scale, CBSE A1–E grading scale & formal signature blocks.',
                    'badge'       => 'CBSE Portrait',
                ],
                [
                    'key'         => 'modern',
                    'name'        => 'Format 3 — Modern Performance Card',
                    'description' => 'Color header banner, student hero stats, subject-wise visual progress bars & GPA badges.',
                    'badge'       => 'Modern Visual',
                ],
                [
                    'key'         => 'compact',
                    'name'        => 'Format 4 — Compact Terminal Slip',
                    'description' => 'Space-efficient single-slip marksheet summary for quick unit tests and terminal exams.',
                    'badge'       => 'Compact Slip',
                ],
            ],
        ],
        'student_id_card' => [
            'label'       => 'Student ID Card',
            'category'    => 'ID Cards',
            'icon'        => 'ri-id-card-line',
            'report_path' => '/id-cards/student',
            'default'     => 'madhuban',
            'formats'     => [
                [
                    'key'         => 'madhuban',
                    'name'        => 'Style 1 — Madhuban Teal Wave',
                    'description' => 'Teal header banner, circular school emblem, centered portrait photo, crisp crimson name & bottom teal wave.',
                    'badge'       => 'CR80 Portrait',
                ],
                [
                    'key'         => 'rn_memorial',
                    'name'        => 'Style 2 — R.N. Memorial Magenta Elegance',
                    'description' => 'Double magenta frame, side session pill badge, centered framed photo & principal signature block.',
                    'badge'       => 'CR80 Portrait',
                ],
            ],
        ],
        'teacher_id_card' => [
            'label'       => 'Teacher & Employee ID Card',
            'category'    => 'ID Cards',
            'icon'        => 'ri-user-star-line',
            'report_path' => '/id-cards/teacher',
            'default'     => 'madhuban',
            'formats'     => [
                [
                    'key'         => 'madhuban',
                    'name'        => 'Style 1 — Madhuban Teal Wave',
                    'description' => 'Teal header banner with school emblem, staff photo, designation, employee code & bottom wave.',
                    'badge'       => 'CR80 Portrait',
                ],
                [
                    'key'         => 'rn_memorial',
                    'name'        => 'Style 2 — R.N. Memorial Magenta Elegance',
                    'description' => 'Double magenta border, staff badge pill, employee details & principal signature section.',
                    'badge'       => 'CR80 Portrait',
                ],
            ],
        ],
        'admit_card' => [
            'label'       => 'Exam Admit Card / Hall Ticket',
            'category'    => 'Examination',
            'icon'        => 'ri-pass-valid-line',
            'report_path' => '/exam/schedule',
            'default'     => 'classic',
            'formats'     => [
                [
                    'key'         => 'classic',
                    'name'        => 'Format 1 — Classic Formal Hall Ticket',
                    'description' => 'Traditional school admit card with student photo, full subject exam timetable & exam instructions.',
                    'badge'       => 'A4 Half / Full',
                ],
                [
                    'key'         => 'modern',
                    'name'        => 'Format 2 — Modern Split Admit Card',
                    'description' => 'Clean header banner with highlighted Roll No badge, exam schedule table & controller seal.',
                    'badge'       => 'Modern Layout',
                ],
                [
                    'key'         => 'compact',
                    'name'        => 'Format 3 — Compact 2-Per-Page Slip',
                    'description' => 'Condensed admit card designed to fit two students neatly on one A4 sheet.',
                    'badge'       => '2-Up A4',
                ],
            ],
        ],
        'fee_receipt' => [
            'label'       => 'Fee Payment Receipt',
            'category'    => 'Fees & Finance',
            'icon'        => 'ri-bill-line',
            'report_path' => '/fees/receipt-history',
            'default'     => 'standard',
            'formats'     => [
                [
                    'key'         => 'standard',
                    'name'        => 'Format 1 — Standard Institutional Receipt',
                    'description' => 'Single full-width detailed fee receipt with fee head breakdown, concession & payment mode.',
                    'badge'       => 'A4 / A5',
                ],
                [
                    'key'         => 'duplicate',
                    'name'        => 'Format 2 — Dual Copy (Office + Student)',
                    'description' => 'Side-by-side or stacked twin receipts with tear line for office record and parent copy.',
                    'badge'       => '2-Copy A4',
                ],
                [
                    'key'         => 'thermal',
                    'name'        => 'Format 3 — Compact Thermal / POS Slip',
                    'description' => 'Narrow 80mm receipt slip optimized for thermal counter printers.',
                    'badge'       => '80mm Thermal',
                ],
            ],
        ],
        'certificate' => [
            'label'       => 'Transfer / Character Certificate',
            'category'    => 'Administration',
            'icon'        => 'ri-file-shield-line',
            'report_path' => '/certificate',
            'default'     => 'classic',
            'formats'     => [
                [
                    'key'         => 'classic',
                    'name'        => 'Format 1 — Classic Bordered Certificate',
                    'description' => 'Ornamental double border, school crest watermark & formal serif institutional typography.',
                    'badge'       => 'Classic A4',
                ],
                [
                    'key'         => 'modern',
                    'name'        => 'Format 2 — Modern Institutional Certificate',
                    'description' => 'Clean contemporary header strip with structured student record summary & seal area.',
                    'badge'       => 'Modern A4',
                ],
            ],
        ],
    ];

    /**
     * Self-healing check so the table exists even before CLI migrate is executed.
     */
    public static function ensureTable(\PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS report_format_settings (
                    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id    BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    report_key   VARCHAR(100) NOT NULL,
                    format_key   VARCHAR(100) NOT NULL,
                    options_json TEXT NULL DEFAULT NULL,
                    updated_by   BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_report_format_firm_key (firm_id, report_key),
                    INDEX idx_report_format_tenant (tenant_id),
                    INDEX idx_report_format_firm (firm_id),
                    INDEX idx_report_format_key (report_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // Best-effort self-healing
        }
    }

    /**
     * Build effective settings map for a firm (defaults <- global firm_id=0 <- firm overrides).
     */
    protected function resolveSettings(\PDO $pdo, int $firmId): array
    {
        $effective = [];
        foreach (self::CATALOG as $reportKey => $meta) {
            $effective[$reportKey] = $meta['default'];
        }

        // Fetch global (firm_id = 0) first, then specific firm_id so firm overrides global
        $stmt = $pdo->prepare(
            'SELECT firm_id, report_key, format_key
             FROM report_format_settings
             WHERE firm_id IN (0, :firm_id)
             ORDER BY firm_id ASC'
        );
        $stmt->execute(['firm_id' => $firmId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $rKey = (string) $row['report_key'];
            $fKey = (string) $row['format_key'];
            if (isset(self::CATALOG[$rKey])) {
                $allowed = array_column(self::CATALOG[$rKey]['formats'], 'key');
                if (in_array($fKey, $allowed, true)) {
                    $effective[$rKey] = $fKey;
                }
            }
        }

        return $effective;
    }

    /**
     * GET /api/report-format-settings
     */
    public function index(Request $request, array $context = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTable($pdo);

        $firmIdParam = $request->query('firm_id');
        $firmId = ($firmIdParam !== null && $firmIdParam !== '') ? max(0, (int) $firmIdParam) : 0;

        $settings = $this->resolveSettings($pdo, $firmId);

        $catalogList = [];
        foreach (self::CATALOG as $key => $meta) {
            $catalogList[] = array_merge(['report_key' => $key], $meta);
        }

        return Response::success([
            'firm_id'  => $firmId,
            'settings' => $settings,
            'catalog'  => $catalogList,
        ]);
    }

    /**
     * POST/PUT /api/report-format-settings
     * Body: { firm_id?: number, settings: { marksheet: "cbse", student_id_card: "rn_memorial", ... } }
     */
    public function save(Request $request, array $context = []): Response
    {
        $data = $request->all();
        $settingsInput = $data['settings'] ?? null;

        if (!is_array($settingsInput) || empty($settingsInput)) {
            return Response::error('settings object is required.', 422);
        }

        $firmId = isset($data['firm_id']) && $data['firm_id'] !== '' && $data['firm_id'] !== null
            ? max(0, (int) $data['firm_id'])
            : 0;

        $authUser = $context['user'] ?? null;
        $updatedBy = $authUser ? (int) $authUser->id : null;
        $tenantId = $authUser && isset($authUser->tenant_id) ? (int) $authUser->tenant_id : null;

        $pdo = Database::pdo();
        self::ensureTable($pdo);

        $upsertSql = 'INSERT INTO report_format_settings
                        (tenant_id, firm_id, report_key, format_key, updated_by)
                      VALUES
                        (:tenant_id, :firm_id, :report_key, :format_key, :updated_by)
                      ON DUPLICATE KEY UPDATE
                        format_key = VALUES(format_key),
                        updated_by = VALUES(updated_by),
                        updated_at = CURRENT_TIMESTAMP';

        $stmt = $pdo->prepare($upsertSql);
        $saved = 0;

        try {
            $pdo->beginTransaction();

            foreach ($settingsInput as $reportKey => $formatKey) {
                $rKey = (string) $reportKey;
                $fKey = (string) $formatKey;

                if (!isset(self::CATALOG[$rKey])) {
                    continue;
                }
                $allowed = array_column(self::CATALOG[$rKey]['formats'], 'key');
                if (!in_array($fKey, $allowed, true)) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    return Response::error("Invalid format '{$fKey}' for report '{$rKey}'.", 422);
                }

                $stmt->execute([
                    'tenant_id'  => $tenantId,
                    'firm_id'    => $firmId,
                    'report_key' => $rKey,
                    'format_key' => $fKey,
                    'updated_by' => $updatedBy,
                ]);
                $saved++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to save report format settings: ' . $e->getMessage(), 500);
        }

        $effective = $this->resolveSettings($pdo, $firmId);

        return Response::success([
            'firm_id'  => $firmId,
            'saved'    => $saved,
            'settings' => $effective,
        ], 'Report format settings saved successfully.');
    }
}


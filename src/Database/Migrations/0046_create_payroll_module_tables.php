<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Payroll Module tables:
 * - payroll_salary_slabs
 * - payroll_components (Allowance & Deductions)
 * - payroll_settings (PF / ESI / TDS)
 * - payroll_employee_profiles (Basic Salary & Statutory flags per employee)
 * - payroll_part_payments
 * - payroll_loans
 * - payroll_loan_transactions
 * - payroll_payslips
 */
class CreatePayrollModuleTablesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
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

        $this->schema(
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

        $this->schema(
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

        $this->schema(
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
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_pep_employee (employee_id),
                INDEX idx_pep_firm (firm_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
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

        $this->schema(
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

        $this->schema(
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

        $this->schema(
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
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS payroll_payslips');
        $this->schema('DROP TABLE IF EXISTS payroll_loan_transactions');
        $this->schema('DROP TABLE IF EXISTS payroll_loans');
        $this->schema('DROP TABLE IF EXISTS payroll_part_payments');
        $this->schema('DROP TABLE IF EXISTS payroll_employee_profiles');
        $this->schema('DROP TABLE IF EXISTS payroll_settings');
        $this->schema('DROP TABLE IF EXISTS payroll_components');
        $this->schema('DROP TABLE IF EXISTS payroll_salary_slabs');
    }
}


<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Add esi_no column to payroll_employee_profiles table for ESI Insurance Number allotment.
 */
class AddEsiNoToPayrollEmployeeProfilesTable extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection();
        $cols = $pdo->query("SHOW COLUMNS FROM payroll_employee_profiles LIKE 'esi_no'")->fetchAll();
        if (empty($cols)) {
            $this->schema('ALTER TABLE payroll_employee_profiles
                ADD COLUMN esi_no VARCHAR(50) NULL DEFAULT NULL AFTER uan_no');
        }
    }

    public function down(): void
    {
        $pdo = $this->connection();
        $cols = $pdo->query("SHOW COLUMNS FROM payroll_employee_profiles LIKE 'esi_no'")->fetchAll();
        if (!empty($cols)) {
            $this->schema('ALTER TABLE payroll_employee_profiles DROP COLUMN esi_no');
        }
    }
}


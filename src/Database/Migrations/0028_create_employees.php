<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * employees table.
 *
 * Employees are linked 1:1 to the `users` table via `user_id`.
 * Specific employee attributes:
 * - employee_type: Teaching, Non-Teaching, Management
 * - attendance_code: Biometric / Attendance ID code
 * - photo: AVIF image path
 */
class CreateEmployeesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS employees (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id         BIGINT UNSIGNED NOT NULL,
                employee_type   ENUM("Teaching", "Non-Teaching", "Management") NOT NULL DEFAULT "Teaching",
                attendance_code VARCHAR(100) NULL DEFAULT NULL,
                photo           VARCHAR(255) NULL DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_employees_user (user_id),
                UNIQUE KEY uq_employees_attendance_code (attendance_code),
                CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                INDEX idx_employees_type (employee_type),
                INDEX idx_employees_attendance_code (attendance_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS employees');
    }
}

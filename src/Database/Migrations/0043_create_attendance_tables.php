<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Attendance Module tables:
 * - student_attendances: Daily attendance per student linked to attendance_legends
 * - employee_attendances: Daily attendance per employee linked to attendance_legends
 */
class CreateAttendanceTablesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS student_attendances (
                id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id            BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                academic_year_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                section_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                student_id           BIGINT UNSIGNED NOT NULL,
                attendance_date      DATE NOT NULL,
                attendance_legend_id BIGINT UNSIGNED NOT NULL,
                remarks              VARCHAR(500) NULL DEFAULT NULL,
                marked_by            BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at           TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_student_attendance_date (student_id, attendance_date),
                CONSTRAINT fk_stu_att_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
                CONSTRAINT fk_stu_att_legend  FOREIGN KEY (attendance_legend_id) REFERENCES attendance_legends (id) ON DELETE RESTRICT,
                CONSTRAINT fk_stu_att_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL,
                CONSTRAINT fk_stu_att_ay      FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                CONSTRAINT fk_stu_att_firm    FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_stu_att_date (attendance_date),
                INDEX idx_stu_att_section_date (section_id, attendance_date),
                INDEX idx_stu_att_ay (academic_year_id),
                INDEX idx_stu_att_firm (firm_id),
                INDEX idx_stu_att_legend (attendance_legend_id),
                INDEX idx_stu_att_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
            'CREATE TABLE IF NOT EXISTS employee_attendances (
                id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id            BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                academic_year_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                employee_id          BIGINT UNSIGNED NOT NULL,
                attendance_date      DATE NOT NULL,
                attendance_legend_id BIGINT UNSIGNED NOT NULL,
                in_time              TIME NULL DEFAULT NULL,
                out_time             TIME NULL DEFAULT NULL,
                remarks              VARCHAR(500) NULL DEFAULT NULL,
                marked_by            BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at           TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_employee_attendance_date (employee_id, attendance_date),
                CONSTRAINT fk_emp_att_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
                CONSTRAINT fk_emp_att_legend   FOREIGN KEY (attendance_legend_id) REFERENCES attendance_legends (id) ON DELETE RESTRICT,
                CONSTRAINT fk_emp_att_ay       FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                CONSTRAINT fk_emp_att_firm     FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_emp_att_date (attendance_date),
                INDEX idx_emp_att_ay (academic_year_id),
                INDEX idx_emp_att_firm (firm_id),
                INDEX idx_emp_att_legend (attendance_legend_id),
                INDEX idx_emp_att_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS employee_attendances');
        $this->schema('DROP TABLE IF EXISTS student_attendances');
    }
}


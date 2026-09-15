<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * students table.
 *
 * Students are linked 1:1 to the `users` table via `user_id`. The profile
 * fields that already live on `users` (title_id, first/middle/last name,
 * gender, dob, doj, city_id, email, mobile_no) are intentionally NOT repeated
 * here — this table only holds student-specific data.
 */
class CreateStudentsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS students (
                id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id           BIGINT UNSIGNED NOT NULL,
                enrollment_number VARCHAR(100) NOT NULL,
                scholar_number    VARCHAR(100) NOT NULL,
                roll_number       VARCHAR(50)  NULL DEFAULT NULL,
                father_email      VARCHAR(255) NULL DEFAULT NULL,
                father_mobile_no  VARCHAR(50)  NULL DEFAULT NULL,
                photo             VARCHAR(255) NULL DEFAULT NULL,
                section_id        BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at        TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_students_user (user_id),
                UNIQUE KEY uq_students_enrollment_number (enrollment_number),
                UNIQUE KEY uq_students_scholar_number (scholar_number),
                CONSTRAINT fk_students_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
                CONSTRAINT fk_students_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE RESTRICT,
                INDEX idx_students_section (section_id),
                INDEX idx_students_roll_number (roll_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS students');
    }
}
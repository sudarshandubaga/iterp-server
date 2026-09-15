<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Adds academic_year_id to student-related tables.
 *
 * Student records currently live in the `users` table (tagged with the
 * "Student" role) as well as in the dedicated `students` table, so the column
 * is added to both to keep them consistent and filterable by the selected
 * academic year.
 */
class AddAcademicYearToStudentsTable extends Migration
{
    public function up(): void
    {
        $this->schema('ALTER TABLE `students`
            ADD COLUMN academic_year_id BIGINT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `idx_students_academic_year` (academic_year_id),
            ADD CONSTRAINT `fk_students_academic_year`
                FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT');

        $this->schema('ALTER TABLE `users`
            ADD COLUMN academic_year_id BIGINT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `idx_users_academic_year` (academic_year_id),
            ADD CONSTRAINT `fk_users_academic_year`
                FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        $this->schema('ALTER TABLE `students` DROP FOREIGN KEY `fk_students_academic_year`');
        $this->schema('ALTER TABLE `students` DROP INDEX `idx_students_academic_year`');
        $this->schema('ALTER TABLE `students` DROP COLUMN academic_year_id');

        $this->schema('ALTER TABLE `users` DROP FOREIGN KEY `fk_users_academic_year`');
        $this->schema('ALTER TABLE `users` DROP INDEX `idx_users_academic_year`');
        $this->schema('ALTER TABLE `users` DROP COLUMN academic_year_id');
    }
}
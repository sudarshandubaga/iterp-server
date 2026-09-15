<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Adds student_category_id to students table.
 */
class AddStudentCategoryToStudentsTable extends Migration
{
    public function up(): void
    {
        $this->schema('ALTER TABLE `students`
            ADD COLUMN student_category_id BIGINT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `idx_students_category` (student_category_id),
            ADD CONSTRAINT `fk_students_category`
                FOREIGN KEY (student_category_id) REFERENCES student_categories (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->schema('ALTER TABLE `students` DROP FOREIGN KEY `fk_students_category`');
        $this->schema('ALTER TABLE `students` DROP INDEX `idx_students_category`');
        $this->schema('ALTER TABLE `students` DROP COLUMN student_category_id');
    }
}

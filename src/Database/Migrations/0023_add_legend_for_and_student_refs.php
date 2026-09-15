<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Adds the "for" (Employee / Student / Both) flag to attendance_legends, and
 * adds student form references (custom field category + document) to users so
 * the "Add Student" flow can associate a category & required document.
 */
class AddLegendForAndStudentRefsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'ALTER TABLE `attendance_legends`
                ADD COLUMN legend_for ENUM("student","employee","both") NOT NULL DEFAULT "both",
                ADD INDEX idx_attendance_legends_for (legend_for)'
        );

        $this->schema(
            'ALTER TABLE `users`
                ADD COLUMN custom_field_category_id BIGINT UNSIGNED NULL DEFAULT NULL,
                ADD COLUMN document_id BIGINT UNSIGNED NULL DEFAULT NULL,
                ADD INDEX idx_users_custom_field_category (custom_field_category_id),
                ADD INDEX idx_users_document (document_id),
                ADD CONSTRAINT fk_users_custom_field_category
                    FOREIGN KEY (custom_field_category_id)
                    REFERENCES custom_field_categories (id) ON DELETE SET NULL,
                ADD CONSTRAINT fk_users_document
                    FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        $this->schema('ALTER TABLE `users` DROP FOREIGN KEY `fk_users_custom_field_category`');
        $this->schema('ALTER TABLE `users` DROP FOREIGN KEY `fk_users_document`');
        $this->schema('ALTER TABLE `users` DROP INDEX `idx_users_custom_field_category`');
        $this->schema('ALTER TABLE `users` DROP INDEX `idx_users_document`');
        $this->schema('ALTER TABLE `users`
            DROP COLUMN custom_field_category_id, DROP COLUMN document_id');

        $this->schema('ALTER TABLE `attendance_legends` DROP INDEX `idx_attendance_legends_for`');
        $this->schema('ALTER TABLE `attendance_legends` DROP COLUMN legend_for');
    }
}
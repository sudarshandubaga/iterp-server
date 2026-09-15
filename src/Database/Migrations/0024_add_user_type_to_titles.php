<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Adds a `user_type` flag (Student / Employee / Both) to the titles table so
 * honorifics can be scoped to a user type as well as gender. The Add Student /
 * Add Employee flows then fetch titles matching the selected user type.
 */
class AddUserTypeToTitlesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'ALTER TABLE `titles`
                ADD COLUMN user_type ENUM("student","employee","both") NOT NULL DEFAULT "both",
                ADD INDEX idx_titles_user_type (user_type)'
        );

        // Keep the existing uniqueness on (name, gender) but let the same title
        // coexist for different user types.
        $this->schema(
            'ALTER TABLE `titles`
                DROP INDEX uq_titles_name_gender,
                ADD UNIQUE KEY uq_titles_name_gender_type (name, gender, user_type)'
        );
    }

    public function down(): void
    {
        $this->schema('ALTER TABLE `titles` DROP INDEX idx_titles_user_type');
        $this->schema(
            'ALTER TABLE `titles`
                DROP INDEX uq_titles_name_gender_type,
                ADD UNIQUE KEY uq_titles_name_gender (name, gender)'
        );
        $this->schema('ALTER TABLE `titles` DROP COLUMN user_type');
    }
}
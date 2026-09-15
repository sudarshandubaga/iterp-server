<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * custom_fields table.
 */
class CreateCustomFieldsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS custom_fields (
                id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                custom_field_category_id  BIGINT UNSIGNED NOT NULL,
                name                      VARCHAR(255) NOT NULL,
                field_type                ENUM("textbox","radio","checkbox","pulldown","textarea","date") NOT NULL,
                data_type                 ENUM(
                                            "numeric",
                                            "alpha_numeric",
                                            "alphabatic",
                                            "alphabatic_special",
                                            "alpha_numeric_special",
                                            "numeric_special"
                                          ) NOT NULL,
                options                   TEXT NULL,
                mandatory                 ENUM("yes","no") NOT NULL DEFAULT "no",
                `show`                    ENUM("yes","no") NOT NULL DEFAULT "yes",
                default_value             VARCHAR(255) NULL,
                validation_message        VARCHAR(255) NULL,
                max_length                INT NULL,
                sort_order                INT NOT NULL DEFAULT 0,
                created_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at                TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_custom_fields_category FOREIGN KEY (custom_field_category_id)
                    REFERENCES custom_field_categories (id) ON DELETE CASCADE,
                INDEX idx_custom_fields_category (custom_field_category_id),
                INDEX idx_custom_fields_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS custom_fields');
    }
}
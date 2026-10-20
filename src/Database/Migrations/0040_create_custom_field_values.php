<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Migration 0040: create custom_field_values table and ensure common student custom fields.
 */
class CreateCustomFieldValuesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS custom_field_values (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id         BIGINT UNSIGNED NOT NULL,
                custom_field_id BIGINT UNSIGNED NOT NULL,
                value           TEXT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cfv_user_field (user_id, custom_field_id),
                INDEX idx_cfv_user (user_id),
                INDEX idx_cfv_field (custom_field_id),
                CONSTRAINT fk_cfv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_cfv_field FOREIGN KEY (custom_field_id) REFERENCES custom_fields (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Ensure default student custom field category exists
        $pdo = $this->connection();
        $catStmt = $pdo->prepare('SELECT id FROM custom_field_categories WHERE (name = "Personal Info" OR name = "Student Info") AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
        $catStmt->execute();
        $catId = $catStmt->fetchColumn();

        if (!$catId) {
            $pdo->prepare('INSERT INTO custom_field_categories (name, short_name, type, sort_order) VALUES ("Student Info", "SI", "student", 1)')->execute();
            $catId = (int) $pdo->lastInsertId();
        }

        // Standard student fields required if not in users/students tables
        $fields = [
            ['name' => 'Aadhar Number',   'type' => 'textbox',  'data' => 'alpha_numeric', 'order' => 10],
            ['name' => 'Father Name',     'type' => 'textbox',  'data' => 'alphabatic',    'order' => 11],
            ['name' => 'Mother Name',     'type' => 'textbox',  'data' => 'alphabatic',    'order' => 12],
            ['name' => 'Social Category', 'type' => 'textbox',  'data' => 'alphabatic',    'order' => 13],
            ['name' => 'Religion',        'type' => 'textbox',  'data' => 'alphabatic',    'order' => 14],
            ['name' => 'Address',         'type' => 'textarea', 'data' => 'alpha_numeric_special', 'order' => 15],
        ];

        $checkStmt = $pdo->prepare('SELECT id FROM custom_fields WHERE name = :name AND deleted_at IS NULL LIMIT 1');
        $insStmt = $pdo->prepare(
            'INSERT INTO custom_fields (custom_field_category_id, name, field_type, data_type, mandatory, `show`, sort_order)
             VALUES (:cat_id, :name, :type, :data, "no", "yes", :order)'
        );

        foreach ($fields as $f) {
            $checkStmt->execute(['name' => $f['name']]);
            if (!$checkStmt->fetchColumn()) {
                $insStmt->execute([
                    'cat_id' => $catId,
                    'name'   => $f['name'],
                    'type'   => $f['type'],
                    'data'   => $f['data'],
                    'order'  => $f['order'],
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS custom_field_values');
    }
}

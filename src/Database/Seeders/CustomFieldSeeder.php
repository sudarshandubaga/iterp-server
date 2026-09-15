<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds sample student custom fields, grouped under the categories created by
 * CustomFieldCategorySeeder. Idempotent — run after CustomFieldCategorySeeder.
 */
class CustomFieldSeeder extends Seeder
{
    public function run(): void
    {
        // [category_name, field definitions...]
        $fields = [
            'Academic Info' => [
                ['name' => 'Admission No.', 'field_type' => 'textbox', 'data_type' => 'alpha_numeric', 'sort_order' => 1],
                ['name' => 'Batch', 'field_type' => 'pulldown', 'data_type' => 'alpha_numeric', 'options' => "Morning\nEvening", 'sort_order' => 2],
                ['name' => 'Previous School', 'field_type' => 'textbox', 'data_type' => 'alpha_numeric', 'sort_order' => 3],
            ],
            'Personal Info' => [
                ['name' => 'Blood Group', 'field_type' => 'pulldown', 'data_type' => 'alphabatic_special', 'options' => "A+\nA-\nB+\nB-\nO+\nO-\nAB+\nAB-", 'sort_order' => 1],
                ['name' => 'Guardian Name', 'field_type' => 'textbox', 'data_type' => 'alphabatic', 'sort_order' => 2],
                ['name' => 'Address', 'field_type' => 'textarea', 'data_type' => 'alpha_numeric_special', 'sort_order' => 3],
            ],
            'Health Details' => [
                ['name' => 'Medical Conditions', 'field_type' => 'textarea', 'data_type' => 'alpha_numeric_special', 'sort_order' => 1],
                ['name' => 'Allergies', 'field_type' => 'checkbox', 'data_type' => 'alpha_numeric_special', 'options' => "Dust\nPollen\nPeanuts\nLactose", 'sort_order' => 2],
            ],
        ];

        // Resolve category ids once for fast reuse.
        $catStmt = $this->connection()->prepare(
            'SELECT id FROM custom_field_categories
             WHERE name = :name AND type = "student" AND deleted_at IS NULL LIMIT 1'
        );

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM custom_fields
             WHERE name = :name AND custom_field_category_id = :cat_id AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO custom_fields
                (custom_field_category_id, name, field_type, data_type, options, mandatory, `show`, sort_order)
             VALUES (:cat_id, :name, :field_type, :data_type, :options, "no", "yes", :sort_order)'
        );

        foreach ($fields as $categoryName => $list) {
            $catStmt->execute(['name' => $categoryName]);
            $catId = $catStmt->fetchColumn();
            if (!$catId) {
                echo "  Category not found: {$categoryName} (skipping its fields)\n";
                continue;
            }

            foreach ($list as $field) {
                $exists->execute(['name' => $field['name'], 'cat_id' => $catId]);
                if ((int) $exists->fetchColumn() > 0) {
                    continue; // idempotent
                }

                $insert->execute([
                    'cat_id'     => $catId,
                    'name'       => $field['name'],
                    'field_type' => $field['field_type'],
                    'data_type'  => $field['data_type'],
                    'options'    => $field['options'] ?? null,
                    'sort_order' => $field['sort_order'],
                ]);
                echo "  Seeded custom field: {$field['name']} ({$categoryName})\n";
            }
        }
    }
}
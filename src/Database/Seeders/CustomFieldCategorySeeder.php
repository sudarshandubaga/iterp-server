<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds student custom field categories (idempotent). These feed the
 * "Add Student" flow so custom fields are grouped by category.
 */
class CustomFieldCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Academic Info', 'short_name' => 'ACAD', 'type' => 'student', 'sort_order' => 1],
            ['name' => 'Personal Info', 'short_name' => 'PERS', 'type' => 'student', 'sort_order' => 2],
            ['name' => 'Health Details', 'short_name' => 'HLTH', 'type' => 'student', 'sort_order' => 3],
        ];

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM custom_field_categories
             WHERE name = :name AND type = :type AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO custom_field_categories (name, short_name, type, sort_order)
             VALUES (:name, :short_name, :type, :sort_order)'
        );

        foreach ($categories as $cat) {
            $exists->execute(['name' => $cat['name'], 'type' => $cat['type']]);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute($cat);
            echo "  Seeded custom field category: {$cat['name']} ({$cat['type']})\n";
        }
    }
}
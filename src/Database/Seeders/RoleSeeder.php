<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds default organisational roles.
 *
 * Roles listed under "Asst. Teacher" below the header are seeded as its
 * children (via parent_id). All others are top-level roles.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Child roles are defined immediately after their parent in the list.
        $roles = [
            // Top-level roles.
            ['name'   => 'Super Admin',    'parent' => null],
            ['name'   => 'Student',        'parent' => null],
            ['name'   => 'Parent',         'parent' => null],
            ['name'   => 'Asst. Teacher',  'parent' => null],
            // Children of Asst. Teacher.
            ['name'   => 'Computer Teacher',  'parent' => 'Asst. Teacher'],
            ['name'   => 'Art & Craft Teacher', 'parent' => 'Asst. Teacher'],
            ['name'   => 'Hindi Teacher',   'parent' => 'Asst. Teacher'],
            ['name'   => 'Dance Teacher',   'parent' => 'Asst. Teacher'],
            ['name'   => 'Sport Teacher',   'parent' => 'Asst. Teacher'],
            ['name'   => 'School Coordinator', 'parent' => 'Asst. Teacher'],
            ['name'   => 'Librarian',       'parent' => 'Asst. Teacher'],
            // Top-level roles.
            ['name'   => 'Account Manager', 'parent' => null],
            ['name'   => 'Conductor',       'parent' => null],
            ['name'   => 'Driver',          'parent' => null],
            ['name'   => 'Guard',           'parent' => null],
            ['name'   => 'Maid',            'parent' => null],
            ['name'   => 'Office Assistant', 'parent' => null],
            ['name'   => 'Principal',       'parent' => null],
            ['name'   => 'Vice Principal',  'parent' => null],
            ['name'   => 'Sweeper',         'parent' => null],
            ['name'   => 'Supervisor',      'parent' => null],
            ['name'   => 'Rickshaw Puller', 'parent' => null],
            ['name'   => 'I.T. Assitant',   'parent' => null],
        ];

        // Load ids of any roles already present so the seeder is idempotent
        // and child roles can be linked to their existing parents.
        $ids = [];
        foreach ($this->connection()->query('SELECT id, name FROM roles') as $row) {
            $ids[$row['name']] = (int) $row['id'];
        }

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM roles WHERE name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO roles (name, parent_id) VALUES (:name, :parent_id)'
        );

        foreach ($roles as $role) {
            $name = $role['name'];
            $parentId = $role['parent'] !== null
                ? ($ids[$role['parent']] ?? null)
                : null;

            $exists->execute(['name' => $name]);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute(['name' => $name, 'parent_id' => $parentId]);
            $ids[$name] = (int) $this->connection()->lastInsertId();

            $parentLabel = $parentId !== null ? " (child of {$role['parent']})" : '';
            echo "  Seeded role: {$name}{$parentLabel}\n";
        }
    }
}
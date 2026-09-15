<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds common honorific titles with their applicable gender.
 */
class TitleSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            ['name' => 'Mr',  'short_name' => 'Mr',   'gender' => 'male',   'user_type' => 'both'],
            ['name' => 'Mrs', 'short_name' => 'Mrs',  'gender' => 'female', 'user_type' => 'both'],
            ['name' => 'Ms',  'short_name' => 'Ms',   'gender' => 'female', 'user_type' => 'both'],
            ['name' => 'Miss', 'short_name' => 'Miss', 'gender' => 'female', 'user_type' => 'both'],
            ['name' => 'Dr',  'short_name' => 'Dr',   'gender' => 'both',   'user_type' => 'both'],
            ['name' => 'Prof', 'short_name' => 'Prof', 'gender' => 'both', 'user_type' => 'employee'],
            ['name' => 'Engg', 'short_name' => 'Er',  'gender' => 'both',   'user_type' => 'both'],
            ['name' => 'Master', 'short_name' => 'Mstr', 'gender' => 'male', 'user_type' => 'student'],
            ['name' => 'Miss (Student)', 'short_name' => 'Ms', 'gender' => 'female', 'user_type' => 'student'],
        ];

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM titles
             WHERE name = :name AND gender = :gender AND user_type = :user_type AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO titles (name, short_name, gender, user_type) VALUES (:name, :short_name, :gender, :user_type)'
        );

        foreach ($titles as $title) {
            $binds = [
                'name'      => $title['name'],
                'gender'    => $title['gender'],
                'user_type' => $title['user_type'],
            ];
            $exists->execute($binds);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute($title);
            echo "  Seeded title: {$title['name']} ({$title['gender']}/{$title['user_type']})\n";
        }
    }
}

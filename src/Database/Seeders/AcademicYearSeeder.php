<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds sample academic years.
 */
class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $years = [
            ['name' => '2024-25', 'start_date' => '2024-04-01', 'end_date' => '2025-03-31'],
            ['name' => '2025-26', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31'],
        ];

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM academic_years WHERE name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO academic_years (name, start_date, end_date)
             VALUES (:name, :start_date, :end_date)'
        );

        foreach ($years as $year) {
            $exists->execute(['name' => $year['name']]);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute($year);
            echo "  Seeded academic year: {$year['name']}\n";
        }
    }
}

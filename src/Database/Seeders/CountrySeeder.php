<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds the country records (currently India).
 *
 * Requires the India dataset from data/india.php.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $dataset = require __DIR__ . '/data/india.php';
        $country = $dataset['country']; // ['name' => 'India', 'short_name' => 'IN']

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM countries WHERE name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO countries (name, short_name)
             VALUES (:name, :short_name)'
        );

        $exists->execute(['name' => $country['name']]);
        if ((int) $exists->fetchColumn() > 0) {
            echo "  Country already exists: {$country['name']}\n";
            return;
        }

        $insert->execute($country);
        echo "  Seeded country: {$country['name']} ({$country['short_name']})\n";
    }
}
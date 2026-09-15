<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds all Indian states / union territories, linked to India.
 *
 * Requires the India dataset from data/india.php. Run after CountrySeeder.
 */
class StateSeeder extends Seeder
{
    public function run(): void
    {
        $dataset = require __DIR__ . '/data/india.php';

        // Find the country id for India.
        $stmt = $this->connection()->prepare(
            'SELECT id FROM countries WHERE name = :name AND deleted_at IS NULL'
        );
        $stmt->execute(['name' => $dataset['country']['name']]);
        $countryId = $stmt->fetchColumn();

        if (!$countryId) {
            echo "  Country not found; run CountrySeeder first.\n";
            return;
        }

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM states WHERE country_id = :country_id AND name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO states (name, short_name, country_id)
             VALUES (:name, :short_name, :country_id)'
        );

        foreach ($dataset['states'] as $name => $state) {
            $binds = [
                'country_id' => $countryId,
                'name'       => $name,
            ];

            $exists->execute($binds);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute([
                'name'       => $name,
                'short_name' => $state['code'],
                'country_id' => $countryId,
            ]);
            echo "  Seeded state: {$name}\n";
        }
    }
}
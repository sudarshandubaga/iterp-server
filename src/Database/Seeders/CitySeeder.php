<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds all Indian cities, linked to their parent state.
 *
 * Requires the India dataset from data/india.php. Run after StateSeeder.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $dataset = require __DIR__ . '/data/india.php';

        // Map state names -> ids for India.
        $countryStmt = $this->connection()->prepare(
            'SELECT id FROM countries WHERE name = :name AND deleted_at IS NULL'
        );
        $countryStmt->execute(['name' => $dataset['country']['name']]);
        $countryId = (int) $countryStmt->fetchColumn();

        $stateIds = [];
        $stateStmt = $this->connection()->prepare(
            'SELECT id FROM states WHERE country_id = :country_id AND name = :name AND deleted_at IS NULL'
        );
        foreach (array_keys($dataset['states']) as $stateName) {
            $stateStmt->execute(['country_id' => $countryId, 'name' => $stateName]);
            $id = $stateStmt->fetchColumn();
            if ($id) {
                $stateIds[$stateName] = (int) $id;
            }
        }

        if ($stateIds === []) {
            echo "  No states found; run StateSeeder first.\n";
            return;
        }

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM cities WHERE state_id = :state_id AND name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO cities (name, short_name, state_id)
             VALUES (:name, :short_name, :state_id)'
        );

        $seeded = 0;
        foreach ($dataset['states'] as $stateName => $state) {
            $stateId = $stateIds[$stateName];

            foreach ($state['cities'] as $city) {
                $binds = ['state_id' => $stateId, 'name' => $city];

                $exists->execute($binds);
                if ((int) $exists->fetchColumn() > 0) {
                    continue; // idempotent
                }

                $insert->execute([
                    'name'       => $city,
                    'short_name' => $this->shortName($city),
                    'state_id'   => $stateId,
                ]);
                $seeded++;
            }
        }

        echo "  Seeded {$seeded} city record(s) across India.\n";
    }

    /**
     * Derive a compact short name from a city name.
     * E.g. "New Delhi" => "ND", "Mumbai" => "MUM".
     */
    private function shortName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        if (!$words) {
            return substr(strtoupper($name), 0, 50);
        }

        if (count($words) === 1) {
            return substr(strtoupper($name), 0, 50);
        }

        $abbr = '';
        foreach ($words as $word) {
            $abbr .= strtoupper(substr($word, 0, 1));
        }
        return substr($abbr, 0, 50);
    }
}
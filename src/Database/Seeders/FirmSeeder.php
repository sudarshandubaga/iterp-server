<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds sample firms, linked to existing cities.
 *
 * Run after CitySeeder.
 */
class FirmSeeder extends Seeder
{
    public function run(): void
    {
        $firms = [
            [
                'name'            => 'Iterp Pvt Ltd',
                'short_name'      => 'ITERP',
                'code'            => 'ITERP-001',
                'registration_no' => 'U72900DL2024PTC000000',
                'address'         => '1st Floor, Tech Park, Connaught Place',
                'city_name'       => 'New Delhi',
                'email'           => 'hello@iterp.local',
                'phone_no'        => '+91 11 0000 0000',
                'fax'             => null,
                'logo'            => null,
            ],
            [
                'name'            => 'Iterp Solutions',
                'short_name'      => 'ISOL',
                'code'            => 'ISOL-002',
                'registration_no' => 'U72200MH2024PTC000000',
                'address'         => 'Bandra Kurla Complex, Bandra East',
                'city_name'       => 'Mumbai',
                'email'           => 'solutions@iterp.local',
                'phone_no'        => '+91 22 0000 0000',
                'fax'             => '+91 22 0000 0001',
                'logo'            => null,
            ],
        ];

        // Look up city ids by name (first match per firm).
        $cityStmt = $this->connection()->prepare(
            'SELECT id FROM cities WHERE name = :name AND deleted_at IS NULL LIMIT 1'
        );

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM firms WHERE code = :code AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO firms (name, short_name, code, registration_no, address, city_id, email, phone_no, fax, logo)
             VALUES (:name, :short_name, :code, :registration_no, :address, :city_id, :email, :phone_no, :fax, :logo)'
        );

        foreach ($firms as $firm) {
            $exists->execute(['code' => $firm['code']]);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $cityStmt->execute(['name' => $firm['city_name']]);
            $cityId = $cityStmt->fetchColumn();

            if (!$cityId) {
                echo "  City not found: {$firm['city_name']} (skipping firm)\n";
                continue;
            }

            $insert->execute([
                'name'            => $firm['name'],
                'short_name'      => $firm['short_name'],
                'code'            => $firm['code'],
                'registration_no' => $firm['registration_no'],
                'address'         => $firm['address'],
                'city_id'         => $cityId,
                'email'           => $firm['email'],
                'phone_no'        => $firm['phone_no'],
                'fax'             => $firm['fax'],
                'logo'            => $firm['logo'],
            ]);
            echo "  Seeded firm: {$firm['name']}\n";
        }
    }
}
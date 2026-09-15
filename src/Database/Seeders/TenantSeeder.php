<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds the default Tenant record(s).
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name'     => 'Iterp',
                'logo'     => null,
                'favicon'  => null,
                'domain'   => 'iterp.local',
                'email'    => 'info@iterp.local',
                'phone_no' => '+91 00000 00000',
            ],
            // Local development hosts, so the strict "no tenant → 404 on every
            // route" rule still passes when running the client on localhost.
            [
                'name'     => 'Iterp (localhost)',
                'logo'     => null,
                'favicon'  => null,
                'domain'   => 'localhost',
                'email'    => 'info@localhost',
                'phone_no' => '+91 00000 00000',
            ],
            [
                'name'     => 'Iterp (127.0.0.1)',
                'logo'     => null,
                'favicon'  => null,
                'domain'   => '127.0.0.1',
                'email'    => 'info@127.0.0.1',
                'phone_no' => '+91 00000 00000',
            ],
        ];

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM tenants WHERE domain = :domain AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO tenants (name, logo, favicon, domain, email, phone_no)
             VALUES (:name, :logo, :favicon, :domain, :email, :phone_no)'
        );

        foreach ($tenants as $data) {
            $exists->execute(['domain' => $data['domain']]);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute($data);
            echo "  Seeded tenant: {$data['name']}\n";
        }
    }
}
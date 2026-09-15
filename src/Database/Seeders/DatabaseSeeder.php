<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Root seeder - orchestrates all individual seeders.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        echo "Seeding database...\n";

        (new TenantSeeder())->run();
        (new CountrySeeder())->run();
        (new StateSeeder())->run();
        (new CitySeeder())->run();
        (new FirmSeeder())->run();
        (new AcademicYearSeeder())->run();
        (new TitleSeeder())->run();
        (new EventSeeder())->run();
        (new RoleSeeder())->run();
        (new CustomFieldCategorySeeder())->run();
        (new CustomFieldSeeder())->run();
        (new DocumentSeeder())->run();

        echo "Seeding complete.\n";
    }
}
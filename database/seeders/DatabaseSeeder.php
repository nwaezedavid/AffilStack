<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PlansSeeder::class,
            CreditPackagesSeeder::class,
            AdminUserSeeder::class,
            FaqItemsSeeder::class,
            SwipeFileEntriesSeeder::class,
            HomepageFeaturesSeeder::class,
            SitePagesSeeder::class,
            PaymentGatewaySettingsSeeder::class,
        ]);
    }
}

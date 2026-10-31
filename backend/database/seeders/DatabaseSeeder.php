<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Safe production seed: roles/permissions, places, catalogue. Never creates users or credentials. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->call('arj:rbac-sync');
        $this->command?->call('arj:import-places');
        $this->call(CatalogueSeeder::class);
        $this->call(MarketPriceSeeder::class);
        $this->call(HindiCatalogueSeeder::class);
        $this->call(ShowcaseSeeder::class);
        $this->call(LegalSeeder::class);
        $this->call(ServiceContentSeeder::class);
        $this->call(BlogSeeder::class);
    }
}

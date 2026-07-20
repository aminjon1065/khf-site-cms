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
            RolePermissionSeeder::class,
            RegionSeeder::class,
            TaxonomySeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            MenuSeeder::class,
            HomeBlockSeeder::class,
            AlertSeeder::class,
            NewsSeeder::class,
            InstructionSeeder::class,
            DocumentSeeder::class,
            ProjectSeeder::class,
            AnnouncementSeeder::class,
            PageSeeder::class,
            ActivitySeeder::class,
            NotificationSeeder::class,
        ]);
    }
}

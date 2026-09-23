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
        // Reference data every instance needs, production included: roles
        // and permissions, regions, the official leadership and structure
        // rosters, news categories, site settings, menu, home page blocks and
        // the system pages the public site renders.
        $this->call([
            RolePermissionSeeder::class,
            RegionSeeder::class,
            LeaderSeeder::class,
            StructureUnitSeeder::class,
            TaxonomySeeder::class,
            SettingSeeder::class,
            MenuSeeder::class,
            HomeBlockSeeder::class,
            PageSeeder::class,
        ]);

        // Demo accounts share a known password and the rest is a development
        // dataset (sample alerts, news, citizen appeals, activity): none of it
        // may reach production (audit J-4). The first administrator is created
        // with `php artisan cms:create-admin`.
        if (app()->isProduction()) {
            $this->command->warn('Production: демо-пользователи и тестовые материалы не создаются. Администратора создайте командой php artisan cms:create-admin.');

            return;
        }

        $this->call([
            UserSeeder::class,
            AlertSeeder::class,
            NewsSeeder::class,
            SourceNewsSeeder::class,
            InstructionSeeder::class,
            DocumentSeeder::class,
            ProjectSeeder::class,
            AnnouncementSeeder::class,
            SourceAnnouncementSeeder::class,
            MediaAssetSeeder::class,
            SubmissionSeeder::class,
            ActivitySeeder::class,
            NotificationSeeder::class,
        ]);
    }
}

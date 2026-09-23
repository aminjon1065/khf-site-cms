<?php

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off data fix: the menu seeder used to copy the Russian label into the
 * Tajik one and leave English empty. The public site used to hide that by
 * replacing labels of known links with its own dictionary; now it shows the
 * CMS label as is, so give the seeded items their real translations. Only a
 * Tajik label that is still the Russian copy (or empty) and an empty English
 * label are filled — anything an editor typed stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (MenuSeeder::MENUS as $location => $items) {
            foreach ($items as [$url, $label]) {
                MenuItem::query()
                    ->where('location', $location)
                    ->where('url', $url)
                    ->get()
                    ->each(function (MenuItem $item) use ($label): void {
                        $current = $item->getTranslations('label');
                        $russian = trim((string) ($current['ru'] ?? ''));
                        $tajik = trim((string) ($current['tg'] ?? ''));
                        $english = trim((string) ($current['en'] ?? ''));
                        $dirty = false;

                        if ($tajik === '' || ($tajik === $russian && $russian === $label['ru'])) {
                            $item->setTranslation('label', 'tg', $label['tg']);
                            $dirty = true;
                        }

                        if ($english === '') {
                            $item->setTranslation('label', 'en', $label['en']);
                            $dirty = true;
                        }

                        if ($dirty) {
                            $item->save();
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        // Content fix only: the untranslated labels are not restored.
    }
};

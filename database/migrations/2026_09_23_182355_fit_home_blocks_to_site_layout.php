<?php

use App\Models\HomeBlock;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off data fix: the public home page now follows the CMS blocks (order,
 * titles, switches) and each section shows only as many items as its layout
 * holds. Bring stored limits down to that capacity, and rename the seeded
 * alerts block, whose title repeated the fixed «Оперативная сводка» strip.
 * Titles editors changed are kept.
 */
return new class extends Migration
{
    /**
     * @var array{ru: string, tg: string, en: string}
     */
    private const SEEDED_ALERTS_TITLE = ['ru' => 'Оперативная сводка', 'tg' => 'Хулосаи оперативӣ', 'en' => 'Operational overview'];

    /**
     * @var array{ru: string, tg: string, en: string}
     */
    private const ALERTS_TITLE = ['ru' => 'Предупреждения', 'tg' => 'Огоҳиҳо', 'en' => 'Warnings'];

    public function up(): void
    {
        HomeBlock::query()->get()->each(function (HomeBlock $block): void {
            $dirty = false;
            $config = $block->config ?? [];
            $max = HomeBlock::MAX_ITEMS[$block->type] ?? null;

            if ($max !== null && isset($config['limit']) && (int) $config['limit'] > $max) {
                $config['limit'] = $max;
                $block->config = $config;
                $dirty = true;
            }

            if ($block->type === 'active_alerts') {
                foreach (self::SEEDED_ALERTS_TITLE as $locale => $seeded) {
                    if ($block->getTranslation('title', $locale, false) === $seeded) {
                        $block->setTranslation('title', $locale, self::ALERTS_TITLE[$locale]);
                        $dirty = true;
                    }
                }
            }

            if ($dirty) {
                $block->save();
            }
        });
    }

    public function down(): void
    {
        // Content fix only.
    }
};

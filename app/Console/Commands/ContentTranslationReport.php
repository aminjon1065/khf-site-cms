<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\District;
use App\Models\Document;
use App\Models\HomeBlock;
use App\Models\Instruction;
use App\Models\Leader;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * C-3: aggregate tg/ru/en translation-gap report for editors preparing for
 * launch. Complements WorkflowService::guardRequiredTranslations() (which
 * blocks one record from publishing if a *required* locale is incomplete —
 * `en` is not required by default, see Setting languages.require_translation)
 * with a bird's-eye view across everything already live, so an editor can
 * see every gap at once instead of one record at a time.
 */
class ContentTranslationReport extends Command
{
    protected $signature = 'content:translation-report';

    protected $description = 'Report tg/ru/en translation gaps across published content and settings';

    /**
     * @var list<string>
     */
    private const LOCALES = ['tg', 'ru', 'en'];

    /**
     * Editorial-workflow models — only published-equivalent rows count.
     *
     * @var array<string, class-string<Model>>
     */
    private const WORKFLOW_MODELS = [
        'Оповещения (alerts)' => Alert::class,
        'Объявления (announcements)' => Announcement::class,
        'Документы (documents)' => Document::class,
        'Инструкции (instructions)' => Instruction::class,
        'Новости (news)' => News::class,
        'Страницы (pages)' => Page::class,
        'Проекты (projects)' => Project::class,
    ];

    /**
     * Always-live reference data — no workflow status, so every row (or
     * every *enabled* row, for the two models that have that flag) counts.
     *
     * @var array<string, class-string<Model>>
     */
    private const REFERENCE_MODELS = [
        'Категории (categories)' => Category::class,
        'Районы (districts)' => District::class,
        'Блоки главной (home_blocks)' => HomeBlock::class,
        'Руководство (leaders)' => Leader::class,
        'Пункты меню (menu_items)' => MenuItem::class,
        'Регионы (regions)' => Region::class,
        'Теги (tags)' => Tag::class,
    ];

    /**
     * Model labels the plan (PROJECT_PLAN.md, C-3) names as required before
     * launch regardless of the `require_translation` setting.
     *
     * @var list<string>
     */
    private const LAUNCH_CRITICAL_LABELS = ['Пункты меню (menu_items)', 'Инструкции (instructions)'];

    /**
     * Setting groups covering "настройки организации" / "экстренные
     * контакты" from the same launch-critical list.
     *
     * @var list<string>
     */
    private const LAUNCH_CRITICAL_SETTING_GROUPS = ['org', 'contacts', 'footer'];

    public function handle(): int
    {
        $this->components->info('Публикуемый контент — только опубликованные/обновлённые/завершённые записи');
        $workflowGaps = $this->reportModels(self::WORKFLOW_MODELS, onlyPublic: true);

        $this->newLine();
        $this->components->info('Справочники — записи всегда видны на сайте, workflow не проходят');
        $referenceGaps = $this->reportModels(self::REFERENCE_MODELS, onlyPublic: false);

        $this->newLine();
        $this->components->info('Настройки (Setting) — ключи с суффиксом _tg/_ru/_en');
        $settingGaps = $this->reportSettings();

        $this->newLine();
        $this->reportLaunchCritical([...$workflowGaps, ...$referenceGaps], $settingGaps);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, class-string<Model>>  $models
     * @return array<string, array<string, int>> label => [locale => missing count]
     */
    private function reportModels(array $models, bool $onlyPublic): array
    {
        $gaps = [];
        $rows = [];

        foreach ($models as $label => $modelClass) {
            /** @var Builder<Model> $query */
            $query = $modelClass::query();

            if ($onlyPublic) {
                $query->whereIn('status', $this->publicStatusValues());
            } elseif (in_array($modelClass, [HomeBlock::class, MenuItem::class], true)) {
                $query->where('enabled', true);
            }

            $total = (clone $query)->count();
            $missing = array_fill_keys(self::LOCALES, 0);

            if ($total > 0) {
                $query->chunkById(200, function (Collection $chunk) use (&$missing): void {
                    foreach ($chunk as $model) {
                        $completeness = $this->completenessFor($model);

                        foreach (self::LOCALES as $locale) {
                            if (($completeness[$locale] ?? 0) < 100) {
                                $missing[$locale]++;
                            }
                        }
                    }
                });
            }

            $gaps[$label] = $missing;
            $rows[] = [
                $label,
                $total,
                $this->formatGap($missing['tg'], $total),
                $this->formatGap($missing['ru'], $total),
                $this->formatGap($missing['en'], $total),
            ];
        }

        $this->table(['Раздел', 'Всего', 'Без tg', 'Без ru', 'Без en'], $rows);

        return $gaps;
    }

    /**
     * Per-locale completeness (0-100) for one record. Document is a special
     * case: its translatable `name` field is a minor label — what actually
     * matters is whether the *file* was uploaded for that language (a
     * separate per-locale media collection, fileLanguages()), so a locale
     * only counts as complete when both are present. Every other model
     * either defines languageCompleteness() itself (Alert) or picks it up
     * from the TracksTranslationCompleteness trait — every remaining
     * translatable model (including the plain reference/taxonomy ones) now
     * uses that trait too, added for this report.
     *
     * @return array<string, int>
     */
    private function completenessFor(Model $model): array
    {
        if ($model instanceof Document) {
            $fileLanguages = $model->fileLanguages();
            /** @var array<string, string> $names */
            $names = $model->getTranslations('name');

            $result = [];

            foreach (self::LOCALES as $locale) {
                $hasName = trim((string) ($names[$locale] ?? '')) !== '';
                $hasFile = $fileLanguages[$locale] ?? false;
                $result[$locale] = ($hasName && $hasFile) ? 100 : 0;
            }

            return $result;
        }

        if (method_exists($model, 'languageCompleteness')) {
            /** @var array<string, int> */
            return $model->languageCompleteness();
        }

        return array_fill_keys(self::LOCALES, 0);
    }

    /**
     * @return list<string>
     */
    private function publicStatusValues(): array
    {
        return array_values(array_map(
            fn (ContentStatus $status): string => $status->value,
            array_filter(ContentStatus::cases(), fn (ContentStatus $status): bool => $status->isPublic()),
        ));
    }

    private function formatGap(int $missing, int $total): string
    {
        return $missing === 0 ? '—' : "{$missing} из {$total}";
    }

    /**
     * Groups Setting rows by "base name" — `name_ru`/`name_tg`/`name_en`
     * become one family keyed `org.name` — and reports any family missing
     * at least one locale. Keys with no locale suffix at all (phone
     * numbers, URLs, booleans) are not translatable and are skipped.
     *
     * @return array<string, list<string>> "group.key" => list of missing locales
     */
    private function reportSettings(): array
    {
        /** @var Collection<int, Setting> $settings */
        $settings = Setting::query()->get(['group', 'key']);

        /** @var array<string, array{group: string, base: string, locales: array<string, bool>}> $families */
        $families = [];

        foreach ($settings as $setting) {
            if (preg_match('/^(.+)_(tg|ru|en)$/', (string) $setting->key, $matches) !== 1) {
                continue;
            }

            $familyKey = "{$setting->group}.{$matches[1]}";
            $families[$familyKey] ??= ['group' => $setting->group, 'base' => $matches[1], 'locales' => []];
            $families[$familyKey]['locales'][$matches[2]] = true;
        }

        $gaps = [];
        $rows = [];

        foreach ($families as $familyKey => $family) {
            $missing = array_values(array_diff(self::LOCALES, array_keys($family['locales'])));

            if ($missing === []) {
                continue;
            }

            $gaps[$familyKey] = $missing;
            $rows[] = [$family['group'], $family['base'], implode(', ', $missing)];
        }

        if ($rows === []) {
            $this->components->info('Все переводимые настройки (по суффиксам _tg/_ru/_en) заполнены для tg/ru/en.');
        } else {
            $this->table(['Группа', 'Ключ', 'Не хватает локалей'], $rows);
        }

        return $gaps;
    }

    /**
     * @param  array<string, array<string, int>>  $contentGaps
     * @param  array<string, list<string>>  $settingGaps
     */
    private function reportLaunchCritical(array $contentGaps, array $settingGaps): void
    {
        $problems = [];

        foreach (self::LAUNCH_CRITICAL_LABELS as $label) {
            $withGaps = array_filter($contentGaps[$label] ?? [], fn (int $count): bool => $count > 0);

            if ($withGaps === []) {
                continue;
            }

            $parts = [];

            foreach ($withGaps as $locale => $count) {
                $parts[] = "{$locale} — {$count}";
            }

            $problems[] = "{$label}: ".implode(', ', $parts);
        }

        foreach ($settingGaps as $familyKey => $missingLocales) {
            [$group] = explode('.', $familyKey, 2);

            if (in_array($group, self::LAUNCH_CRITICAL_SETTING_GROUPS, true)) {
                $problems[] = "{$familyKey}: не хватает ".implode(', ', $missingLocales);
            }
        }

        if ($problems === []) {
            $this->components->info(
                'Обязательное к запуску (меню, настройки организации, инструкции населению, экстренные контакты) — переведено полностью.',
            );

            return;
        }

        $this->components->warn('Обязательное к запуску не переведено полностью:');

        foreach ($problems as $problem) {
            $this->line("  - {$problem}");
        }
    }
}

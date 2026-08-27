<?php

namespace App\Services;

use App\Http\Resources\Api\PublicAlertResource;
use App\Http\Resources\Api\PublicAnnouncementResource;
use App\Http\Resources\Api\PublicDocumentResource;
use App\Http\Resources\Api\PublicInstructionResource;
use App\Http\Resources\Api\PublicNewsResource;
use App\Http\Resources\Api\PublicProjectResource;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\HomeBlock;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Project;
use App\Models\Region;
use App\Support\PublicLocale;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Builds the complete public home DTO with a bounded, explicit query plan.
 */
final class HomePageReadModel
{
    public function __construct(
        private readonly AlertMapService $map,
        private readonly PublicSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $locale): array
    {
        $blocks = HomeBlock::query()
            ->select(['id', 'type', 'title', 'config'])
            ->where('enabled', true)
            ->orderBy('sort')
            ->get();

        $activeAlertsQuery = Alert::query()
            ->select([
                'id',
                'slug',
                'severity',
                'status',
                'hazard_type',
                'title',
                'summary',
                'territory_type',
                'territory_note',
                'published_at',
                'starts_at',
                'ends_at',
            ])
            ->active()
            ->with('regions:id,code,name');
        PublicLocale::available($activeAlertsQuery, 'title', $locale);
        $activeAlerts = $activeAlertsQuery->get()
            ->sortByDesc(fn (Alert $alert): int => $alert->severity->weight())
            ->values();

        $regions = Region::query()
            ->select(['id', 'code', 'name', 'sort'])
            ->orderBy('sort')
            ->get();

        $snapshot = $this->map->snapshotFor($activeAlerts, $regions, $locale);
        $settings = $this->settings->resolve($locale)['data'];

        $newsQuery = News::query()
            ->select([
                'id',
                'slug',
                'title',
                'summary',
                'category_id',
                'cover_alt',
                'is_pinned',
                'published_at',
            ])
            ->public()
            ->with([
                'category:id,name',
                'media' => fn (Relation $query): Relation => $query->where('collection_name', 'cover'),
            ])
            ->where('show_on_home', true)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
        PublicLocale::available($newsQuery, 'title', $locale);
        $news = $newsQuery->limit($this->limitOf($blocks, 'latest_news', 5))->get();

        $instructionsQuery = Instruction::query()
            ->select(['id', 'slug', 'name', 'summary', 'hazard_type', 'is_priority', 'sort'])
            ->public()
            ->ordered()
            ->with([
                'media' => fn (Relation $query): Relation => $query->where('collection_name', 'image'),
            ]);
        PublicLocale::available($instructionsQuery, 'name', $locale);
        $instructions = $instructionsQuery
            ->limit($this->limitOf($blocks, 'instructions', 4))
            ->get();

        $documentsQuery = Document::query()
            ->select(['id', 'name', 'doc_type', 'number', 'section', 'doc_date'])
            ->public()
            ->ordered()
            ->with([
                'media' => fn (Relation $query): Relation => $query->whereIn(
                    'collection_name',
                    ['file_tg', 'file_ru', 'file_en'],
                ),
            ]);
        PublicLocale::available($documentsQuery, 'name', $locale);
        $documents = $documentsQuery
            ->limit($this->limitOf($blocks, 'documents', 3))
            ->get();

        $announcementsQuery = Announcement::query()
            ->select([
                'id',
                'slug',
                'title',
                'body',
                'kind',
                'org',
                'deadline',
                'application_url',
            ])
            ->public()
            ->ordered();
        PublicLocale::available($announcementsQuery, 'title', $locale);
        $announcements = $announcementsQuery
            ->limit($this->limitOf($blocks, 'announcements', 3))
            ->get();

        $projectsQuery = Project::query()
            ->select([
                'id',
                'slug',
                'title',
                'summary',
                'lifecycle_status',
                'years',
                'partner',
                'budget',
                'sort',
                'published_at',
            ])
            ->public()
            ->ordered()
            ->with([
                'media' => fn (Relation $query): Relation => $query->where('collection_name', 'cover'),
            ]);
        PublicLocale::available($projectsQuery, 'title', $locale);
        $projects = $projectsQuery
            ->limit($this->limitOf($blocks, 'projects', 2))
            ->get();

        return [
            'blocks' => $blocks->map(fn (HomeBlock $block): array => [
                'type' => $block->type,
                'title' => $block->getTranslation('title', $locale, false),
                'config' => $block->config ?? [],
            ])->values()->all(),
            'alerts' => [
                'state' => $snapshot['state'],
                'count' => $snapshot['count'],
                'regions' => $snapshot['regions'],
                'items' => PublicAlertResource::collection(
                    $activeAlerts->take($this->limitOf($blocks, 'active_alerts', 3)),
                )->resolve(),
            ],
            'news' => PublicNewsResource::collection($news)->resolve(),
            'instructions' => PublicInstructionResource::collection($instructions)->resolve(),
            'documents' => PublicDocumentResource::collection($documents)->resolve(),
            'announcements' => PublicAnnouncementResource::collection($announcements)->resolve(),
            'projects' => PublicProjectResource::collection($projects)->resolve(),
            'indicators' => $this->indicators($blocks, $locale),
            'emergency_contacts' => [
                'emergency_number' => data_get($settings, 'org.emergency_number', '112'),
                'trust_phone' => data_get($settings, 'org.trust_phone'),
                'duty_phone' => data_get($settings, 'contacts.duty_phone'),
                'email' => data_get($settings, 'org.email'),
                'services' => data_get($settings, 'emergency_services', []),
            ],
        ];
    }

    /**
     * Показатели ведомства для главной: число и подпись на языке страницы.
     *
     * Считать их система не может — «спасательных операций» и «человек
     * спасено» нет ни в одной таблице, эти цифры приходят из отчётности.
     * Их вводит редактор в настройках блока.
     *
     * Запись без подписи на запрошенном языке пропускается: число без
     * пояснения ничего не сообщает.
     *
     * @param  EloquentCollection<int, HomeBlock>  $blocks
     * @return list<array{value: string, label: string}>
     */
    private function indicators(EloquentCollection $blocks, string $locale): array
    {
        $config = $blocks->firstWhere('type', 'indicators')?->config;
        $items = is_array($config['items'] ?? null) ? $config['items'] : [];
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = is_string($item['value'] ?? null) ? trim($item['value']) : '';
            $labels = is_array($item['label'] ?? null) ? $item['label'] : [];
            $label = trim((string) ($labels[$locale] ?? ''));

            if ($value === '' || $label === '') {
                continue;
            }

            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }

    /**
     * @param  EloquentCollection<int, HomeBlock>  $blocks
     */
    private function limitOf(EloquentCollection $blocks, string $type, int $default): int
    {
        $config = $blocks->firstWhere('type', $type)?->config;
        $configured = is_array($config) ? ($config['limit'] ?? $default) : $default;

        return min(max((int) $configured, 1), 50);
    }
}

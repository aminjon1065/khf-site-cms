<?php

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\Leader;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\StructureUnit;
use Database\Seeders\RegionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('never selects every column from payload-heavy public content tables', function () {
    Alert::factory()->published()->create();
    Announcement::factory()->published()->create();
    Document::factory()->published()->create();
    Instruction::factory()->published()->create();
    Leader::factory()->create();
    News::factory()->published()->create();
    Page::factory()->published()->create();
    Project::factory()->published()->create();
    StructureUnit::factory()->create();
    Category::query()->create([
        'type' => 'news',
        'slug' => 'test-category',
        'name' => ['ru' => 'Категория', 'tg' => 'Категория', 'en' => 'Category'],
        'sort' => 1,
    ]);
    MenuItem::query()->create([
        'label' => ['ru' => 'Главная', 'tg' => 'Асосӣ', 'en' => 'Home'],
        'url' => '/',
        'location' => 'main',
        'sort' => 1,
        'enabled' => true,
    ]);
    $this->seed(RegionSeeder::class);

    Cache::flush();
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    foreach ([
        '/api/v1/alerts?locale=ru',
        '/api/v1/announcements?locale=ru',
        '/api/v1/categories?locale=ru',
        '/api/v1/documents?locale=ru',
        '/api/v1/instructions?locale=ru',
        '/api/v1/leadership?locale=ru',
        '/api/v1/menu?locale=ru',
        '/api/v1/news?locale=ru',
        '/api/v1/pages?locale=ru',
        '/api/v1/projects?locale=ru',
        '/api/v1/regions/directory?locale=ru',
        '/api/v1/structure?locale=ru',
    ] as $uri) {
        $this->getJson($uri)->assertOk();
    }

    foreach ([
        'alerts',
        'announcements',
        'categories',
        'documents',
        'instructions',
        'leaders',
        'menu_items',
        'news',
        'pages',
        'projects',
        'regions',
        'structure_units',
    ] as $table) {
        $tableQueries = array_values(array_filter($queries, function (string $sql) use ($table): bool {
            $normalized = str_replace(['"', '`'], '', $sql);

            return str_contains($normalized, "from {$table}")
                && ! str_contains($normalized, 'count(*)');
        }));

        expect($tableQueries)
            ->not->toBeEmpty("No query captured for {$table}.")
            ->each->not->toMatch('/select\s+(?:["`]?'.$table.'["`]?\.)?\*/');
    }
});

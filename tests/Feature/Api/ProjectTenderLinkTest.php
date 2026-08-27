<?php

use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use App\Models\Project;

// Тендеры публикуются как объявления, но принадлежат проекту. Связи не было
// вовсе: страница проекта не могла показать свои тендеры, а из объявления
// некуда было вернуться к проекту.

it('exposes the project on a tender detail response', function () {
    $project = Project::factory()->published()->create([
        'title' => ['ru' => 'Раннее оповещение', 'tg' => '', 'en' => ''],
    ]);
    $tender = Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Tender,
        'project_id' => $project->id,
    ]);

    $response = $this->getJson("/api/v1/announcements/{$tender->slug}?locale=ru")
        ->assertOk();

    expect($response->json('data.project.slug'))->toBe($project->slug)
        ->and($response->json('data.project.title'))->toBe('Раннее оповещение');
});

it('leaves the project null for an announcement outside any project', function () {
    $vacancy = Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Vacancy,
        'project_id' => null,
    ]);

    $response = $this->getJson("/api/v1/announcements/{$vacancy->slug}?locale=ru")
        ->assertOk();

    expect($response->json('data.project'))->toBeNull();
});

it('lists the tenders of a project on its detail response', function () {
    $project = Project::factory()->published()->create();
    $tender = Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Tender,
        'project_id' => $project->id,
        'title' => ['ru' => 'Закупка инструмента', 'tg' => '', 'en' => ''],
    ]);

    // Вакансия того же проекта в блок тендеров попадать не должна.
    Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Vacancy,
        'project_id' => $project->id,
    ]);

    // Тендер чужого проекта — тем более.
    Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Tender,
        'project_id' => Project::factory()->published()->create()->id,
    ]);

    $response = $this->getJson("/api/v1/projects/{$project->slug}?locale=ru")
        ->assertOk();

    expect($response->json('data.tenders'))->toHaveCount(1)
        ->and($response->json('data.tenders.0.slug'))->toBe($tender->slug)
        ->and($response->json('data.tenders.0.title'))->toBe('Закупка инструмента');
});

it('keeps the tender when its project is deleted', function () {
    // nullOnDelete, а не каскад: объявление о тендере остаётся в архиве как
    // самостоятельный документ, даже если проект убрали.
    $project = Project::factory()->published()->create();
    $tender = Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Tender,
        'project_id' => $project->id,
    ]);

    $project->forceDelete();

    expect(Announcement::query()->whereKey($tender->id)->value('project_id'))
        ->toBeNull();
});

it('does not query the project for every row of the announcements list', function () {
    Announcement::factory()->count(5)->published()->create([
        'kind' => AnnouncementKind::Tender,
        'project_id' => Project::factory()->published()->create()->id,
    ]);

    // Список не выводит проект, поэтому и грузить связь не должен: иначе
    // каждая строка стоила бы отдельного запроса.
    //
    // Порог = фактическое число запросов, а не «с запасом»: при запасе в
    // несколько запросов регрессия на пяти строках укладывалась бы в него и
    // сторож молчал бы. Если запросов станет больше — тест обязан упасть,
    // даже если рост окажется законным: тогда порог поднимают осознанно.
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson('/api/v1/announcements?locale=ru')->assertOk();

    expect($queries)->toBe(2);
});

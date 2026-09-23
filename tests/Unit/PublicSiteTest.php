<?php

use App\Support\PublicSite;

test('maps records to the routes of the public site', function (string $type, ?string $slug, ?string $expected) {
    expect(PublicSite::pathFor($type, $slug))->toBe($expected);
})->with([
    'news' => ['news', 'storm', '/news/storm'],
    'instructions live under guides' => ['instruction', 'earthquake', '/guides/earthquake'],
    'announcement' => ['announcement', 'vacancy-112', '/announcements/vacancy-112'],
    'project' => ['project', 'early-warning', '/projects/early-warning'],
    'alert' => ['alert', 'mudflow-khatlon', '/alerts/mudflow-khatlon'],
    'page with its own section' => ['page', 'leadership', '/leadership'],
    'ordinary page' => ['page', 'history', '/pages/history'],
    'documents have no page of their own' => ['document', 'law-112', null],
    'no slug yet' => ['news', null, null],
]);

test('serves the Tajik content locale under the tj segment', function () {
    expect(PublicSite::localeSegment('tg'))->toBe('tj')
        ->and(PublicSite::localeSegment('ru'))->toBe('ru')
        ->and(PublicSite::localeSegment('en'))->toBe('en');
});

test('protects the pages the site links to by address', function (?string $slug, bool $protected) {
    expect(PublicSite::isSystemPage($slug))->toBe($protected);
})->with([
    ['about', true],
    ['structure', true],
    ['privacy', true],
    ['accessibility', true],
    ['history', false],
    [null, false],
]);

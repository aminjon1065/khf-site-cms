<?php

use App\Models\News;
use App\Support\ContentTypes;
use Illuminate\Database\Eloquent\Model;

test('maps every content type to its module slug', function (string $type, string $module) {
    expect(ContentTypes::module($type))->toBe($module);
})->with([
    ['alert', 'alerts'],
    ['news', 'news'],
    ['instruction', 'instructions'],
    ['document', 'documents'],
    ['project', 'projects'],
    ['announcement', 'announcements'],
    ['page', 'pages'],
]);

test('module falls back to the type itself for unknown keys', function () {
    expect(ContentTypes::module('unknown'))->toBe('unknown');
});

test('slugFor resolves the type key from a model class', function () {
    expect(ContentTypes::slugFor(new News))->toBe('news')
        ->and(ContentTypes::slugFor(new class extends Model {}))->toBeNull();
});

test('label returns a human label with a fallback', function () {
    expect(ContentTypes::label('news'))->toBe('Новость')
        ->and(ContentTypes::label('missing'))->toBe('Материал');
});

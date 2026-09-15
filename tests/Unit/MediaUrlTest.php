<?php

use App\Support\MediaUrl;

test('strips scheme and host from absolute media urls keeping query', function (string $url, string $expected) {
    expect(MediaUrl::toRelative($url))->toBe($expected);
})->with([
    'plain' => ['http://127.0.0.1:8848/storage/36/photo.jpg', '/storage/36/photo.jpg'],
    'https with domain' => ['https://khf.tj/storage/1/a.png?v=123', '/storage/1/a.png?v=123'],
    'root path' => ['http://localhost/storage/x.webp', '/storage/x.webp'],
    'subpath' => ['https://cdn.example.com/media/img/file.jpg', '/media/img/file.jpg'],
]);

test('passes root-relative and malformed urls through unchanged', function (string $url) {
    expect(MediaUrl::toRelative($url))->toBe($url);
})->with([
    'already relative' => ['/storage/36/photo.jpg?v=1786955963'],
    'bare path' => ['/media/private/1'],
    'not a url' => ['photo.jpg'],
    'empty' => [''],
]);

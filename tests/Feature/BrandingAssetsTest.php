<?php

it('links the Committee favicons and web manifest from the favicon folder', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    preg_match_all('/<link rel="(?:icon|apple-touch-icon|manifest)" href="([^"]+)"/', $html, $matches);

    expect($matches[1])->toBe([
        '/favicon/favicon.ico',
        '/favicon/favicon-32x32.png',
        '/favicon/favicon-16x16.png',
        '/favicon/apple-touch-icon.png',
        '/favicon/site.webmanifest',
    ]);

    foreach ($matches[1] as $href) {
        expect(public_path(ltrim($href, '/')))->toBeFile();
    }
});

it('points the web manifest at icons that exist', function () {
    $manifest = json_decode(
        (string) file_get_contents(public_path('favicon/site.webmanifest')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['name'])->not->toBeEmpty()
        ->and($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

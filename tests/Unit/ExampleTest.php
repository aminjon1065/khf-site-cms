<?php

test('the sidebar contains a single control center destination', function () {
    $navigation = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/navigation.tsx');

    expect($navigation)
        ->not->toBeFalse()
        ->and(substr_count((string) $navigation, "key: 'control'"))->toBe(1)
        ->and(substr_count((string) $navigation, 'href: control.url()'))->toBe(1)
        ->and((string) $navigation)->not->toContain("key: 'map'");
});

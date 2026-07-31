<?php

it('versions media urls before assigning immutable derivative caching', function () {
    expect(config('media-library.version_urls'))->toBeTrue();

    $apacheConfiguration = file_get_contents(public_path('.htaccess'));

    expect($apacheConfiguration)
        ->toContain('^/storage/.+/conversions/')
        ->toContain('public, max-age=31536000, immutable');
});

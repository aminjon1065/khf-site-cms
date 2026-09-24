<?php

use App\Support\UploadLimits;

test('accepts PHP set up for the uploads the CMS allows', function () {
    expect(UploadLimits::serverProblems([
        'upload_max_filesize' => '20M',
        'post_max_size' => '128M',
        'max_file_uploads' => '40',
    ]))->toBe([]);
});

test('names each PHP default that turns uploads away', function () {
    $problems = UploadLimits::serverProblems([
        'upload_max_filesize' => '2M',
        'post_max_size' => '8M',
        'max_file_uploads' => '20',
    ]);

    expect($problems)->toHaveCount(3)
        ->and($problems[0])->toContain('до 2 МБ (upload_max_filesize = 2M)')
        ->and($problems[1])->toContain('до 8 МБ (post_max_size = 8M)')
        ->and($problems[2])->toContain('не больше 20 файлов');
});

test('reads sizes the way php.ini writes them', function () {
    expect(UploadLimits::serverProblems([
        'upload_max_filesize' => '1G',
        'post_max_size' => '131072K',
        'max_file_uploads' => '50',
    ]))->toBe([]);
});

test('treats post_max_size = 0 as no limit', function () {
    expect(UploadLimits::serverProblems([
        'upload_max_filesize' => '20M',
        'post_max_size' => '0',
        'max_file_uploads' => '40',
    ]))->toBe([]);
});

<?php

use Mews\Purifier\Facades\Purifier;
use Tests\TestCase;

uses(TestCase::class);

test('purifier news profile allows aside with re-callout and data-callout-type', function () {
    $input = '<aside class="re-callout re-callout-warning" data-callout-type="warning"><p>Штормовое предупреждение: лавинная опасность в ГБАО.</p></aside>';
    $cleaned = Purifier::clean($input, 'news');

    expect($cleaned)->toContain('aside')
        ->and($cleaned)->toContain('data-callout-type="warning"')
        ->and($cleaned)->toContain('re-callout')
        ->and($cleaned)->toContain('Штормовое предупреждение');
});

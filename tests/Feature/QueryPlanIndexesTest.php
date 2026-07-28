<?php

use Illuminate\Support\Facades\Schema;

it('creates only the composite indexes proven by production-like query plans', function () {
    $menuIndexes = collect(Schema::getIndexes('menu_items'))->keyBy('name');
    $submissionIndexes = collect(Schema::getIndexes('submissions'))->keyBy('name');

    expect($menuIndexes)
        ->toHaveKey('menu_items_public_tree_index')
        ->and($menuIndexes['menu_items_public_tree_index']['columns'])
        ->toBe(['location', 'enabled', 'sort', 'parent_id'])
        ->and($submissionIndexes)
        ->toHaveKey('submissions_status_created_index')
        ->and($submissionIndexes['submissions_status_created_index']['columns'])
        ->toBe(['status', 'created_at', 'id']);
});

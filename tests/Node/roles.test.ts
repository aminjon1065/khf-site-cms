import assert from 'node:assert/strict';
import test from 'node:test';
import {
    grantedRights,
    rightsSummary,
    toggleRight,
} from '../../resources/js/lib/roles.ts';

// Sections and actions as the server shares them (RoleController::grid()).
const actions = [
    { value: 'view', label: 'Просмотр' },
    { value: 'create', label: 'Создание' },
    { value: 'edit', label: 'Правка' },
    { value: 'delete', label: 'Удаление' },
    { value: 'publish', label: 'Публикация' },
    { value: 'approve', label: 'Согласование' },
];
const news = {
    value: 'news',
    label: 'Новости и заявления',
    actions: ['view', 'create', 'edit', 'delete', 'publish', 'approve'],
};
const taxonomy = {
    value: 'taxonomy',
    label: 'Рубрики и метки',
    actions: ['view', 'create', 'edit', 'delete'],
};

test('a section summary says «everything», «view only» or nothing', () => {
    const all = Object.fromEntries(news.actions.map((a) => [a, true]));

    assert.equal(rightsSummary(news, all, actions), 'Всё');
    assert.equal(rightsSummary(news, { view: true }, actions), 'Просмотр');
    assert.equal(rightsSummary(news, {}, actions), '—');
    assert.equal(rightsSummary(news, undefined, actions), '—');
});

test('a summary lists what a role does beyond viewing', () => {
    assert.equal(
        rightsSummary(
            news,
            { view: true, create: true, edit: true, publish: true },
            actions,
        ),
        'Создание, правка, публикация',
    );
    assert.equal(
        rightsSummary(
            taxonomy,
            { view: true, create: true, edit: true },
            actions,
        ),
        'Создание, правка',
    );
});

test('a summary tells when materials go out through approval', () => {
    assert.equal(
        rightsSummary(news, { view: true, create: true, edit: true }, actions),
        'Создание, правка · публикация через согласование',
    );
    // Nothing to approve where nothing is published.
    assert.equal(
        rightsSummary(taxonomy, { view: true, edit: true }, actions),
        'Правка',
    );
});

test('any right in a section brings seeing it', () => {
    assert.deepEqual(toggleRight([], 'news', 'publish', true), [
        'news.publish',
        'news.view',
    ]);
    assert.deepEqual(toggleRight(['news.view'], 'news', 'edit', true), [
        'news.view',
        'news.edit',
    ]);
});

test('without seeing a section no other right in it stays', () => {
    assert.deepEqual(
        toggleRight(
            ['news.view', 'news.edit', 'alerts.view'],
            'news',
            'view',
            false,
        ),
        ['alerts.view'],
    );
    assert.deepEqual(
        toggleRight(['news.view', 'news.edit'], 'news', 'edit', false),
        ['news.view'],
    );
});

test('a matrix turns back into right names', () => {
    assert.deepEqual(
        grantedRights({
            news: { view: true, create: false, publish: true },
            media: { view: false },
        }),
        ['news.view', 'news.publish'],
    );
});

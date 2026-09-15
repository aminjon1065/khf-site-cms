import assert from 'node:assert/strict';
import test from 'node:test';
import { navItemAllowed } from '../../resources/js/lib/permissions.ts';

const nothing = () => false;
const only = (ability: string) => (a: string) => a === ability;

test('items without a permission are always visible', () => {
    assert.equal(navItemAllowed({}, nothing), true);
    assert.equal(navItemAllowed({ permission: undefined }, nothing), true);
});

test('a single permission string requires that exact ability', () => {
    assert.equal(
        navItemAllowed({ permission: 'news.view' }, only('news.view')),
        true,
    );
    assert.equal(
        navItemAllowed({ permission: 'news.view' }, only('alerts.view')),
        false,
    );
});

test('a permission list grants visibility when any ability matches', () => {
    const approveAny = ['alerts.approve', 'news.approve', 'pages.approve'];

    assert.equal(
        navItemAllowed({ permission: approveAny }, only('news.approve')),
        true,
    );
    assert.equal(
        navItemAllowed({ permission: approveAny }, only('news.edit')),
        false,
    );
});

test('an empty permission list hides the item (mirrors abort_if)', () => {
    assert.equal(
        navItemAllowed({ permission: [] }, only('news.approve')),
        false,
    );
});

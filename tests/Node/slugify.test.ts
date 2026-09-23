import assert from 'node:assert/strict';
import test from 'node:test';
import {
    limitSlug,
    SLUG_MAX_LENGTH,
    slugify,
} from '../../resources/js/lib/slugify.ts';

test('a long title becomes an address the site can hold, cut between words', () => {
    const title = Array.from({ length: 40 }, () => 'Землетрясение').join(' ');
    const slug = slugify(title);

    assert.ok(slug.length <= SLUG_MAX_LENGTH);
    assert.ok(slug.length > SLUG_MAX_LENGTH / 2);
    assert.match(slug, /^(zemletryasenie-)*zemletryasenie$/);
});

test('a short title is left as it is', () => {
    assert.equal(slugify('Паводок в Хатлоне'), 'pavodok-v-khatlone');
});

test('a cut that lands on a word boundary keeps the whole word', () => {
    assert.equal(limitSlug('aaa-bbb-ccc', 7), 'aaa-bbb');
    assert.equal(limitSlug('aaa-bbb-ccc', 9), 'aaa-bbb');
});

test('a single overlong word is cut where the limit falls', () => {
    assert.equal(limitSlug('a-' + 'b'.repeat(20), 10), 'a-bbbbbbbb');
});

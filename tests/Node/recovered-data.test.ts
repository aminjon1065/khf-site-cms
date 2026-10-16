import assert from 'node:assert/strict';
import test from 'node:test';
import { conformToForm } from '../../resources/js/lib/recovered-data.ts';

const form = {
    title: { ru: 'Текущий', tg: 'Ҷорӣ', en: '' },
    summary: { ru: '', tg: '', en: '' },
    seo: {
        ru: { title: '', description: '' },
        tg: { title: '', description: '' },
    },
    cover_alt: '',
    category_id: 3 as number | null,
    is_pinned: true,
    tags: [1, 2],
    cover: null,
};

test('a model snapshot takes the shape of the form', () => {
    // As EditorialContent::snapshot() stores it: an empty translation map
    // is [], a missing language has no key, an empty field is null.
    const version = conformToForm(form, {
        title: { ru: 'Первая версия' },
        summary: [],
        seo: { ru: { title: 'SEO' } },
        cover_alt: null,
        category_id: null,
        is_pinned: false,
    });

    assert.deepEqual(version, {
        title: { ru: 'Первая версия', tg: '', en: '' },
        summary: { ru: '', tg: '', en: '' },
        seo: {
            ru: { title: 'SEO', description: '' },
            tg: { title: '', description: '' },
        },
        cover_alt: '',
        category_id: null,
        is_pinned: false,
    });
});

test('fields the form does not have are left out, the rest untouched', () => {
    const version = conformToForm(form, {
        title: { ru: 'X', tg: 'Y', en: 'Z' },
        status: 'published',
    });

    assert.deepEqual(Object.keys(version), ['title']);
});

test('an empty version from PHP ([]) changes nothing', () => {
    assert.deepEqual(conformToForm(form, []), {});
});

test('form data itself passes through unchanged', () => {
    assert.deepEqual(conformToForm(form, form), form);
});

import assert from 'node:assert/strict';
import test from 'node:test';
import {
    hasAnyTranslation,
    languageChecks,
    missingVersionNotice,
    previewLocale,
} from '../../resources/js/lib/publication-languages.ts';

const only = (title: string) => ({ tg: title, ru: '', en: '' });

test('a material filled in one language passes the blocking check', () => {
    const [anyVersion, tg, ru, en] = languageChecks(
        { tg: 100, ru: 0, en: 0 },
        only('Сарлавҳа'),
    );

    assert.deepEqual(anyVersion, {
        label: 'Хотя бы одна языковая версия заполнена',
        ok: true,
        blocking: true,
        detail: null,
    });
    assert.equal(tg.ok, true);
    assert.equal(ru.blocking, undefined);
    assert.equal(
        ru.detail,
        'Нет заголовка — на русской версии сайта материал не появится.',
    );
    assert.equal(
        en.detail,
        'Нет заголовка — на английской версии сайта материал не появится.',
    );
});

test('publication is blocked until some language version is complete', () => {
    const [anyVersion, , ru] = languageChecks(
        { tg: 0, ru: 60, en: 0 },
        { tg: '', ru: 'Заголовок', en: '' },
    );

    assert.equal(anyVersion.ok, false);
    assert.equal(anyVersion.blocking, true);
    assert.equal(ru.detail, 'Заполнена на 60%.');
});

test('named materials speak of a missing name', () => {
    const checks = languageChecks(
        { tg: 100, ru: 0, en: 0 },
        only('Қарор'),
        'названия',
    );

    assert.equal(
        checks[2].detail,
        'Нет названия — на русской версии сайта материал не появится.',
    );
    assert.equal(
        missingVersionNotice('en', 'названия'),
        'Для EN нет названия — на английской версии сайта материал не появится.',
    );
});

test('the preview opens the edited language, or the first filled one', () => {
    const versions = {
        tg: { title: 'Сарлавҳа' },
        ru: { title: '' },
        en: { title: 'Title' },
    };

    assert.equal(previewLocale(versions, 'en'), 'en');
    assert.equal(previewLocale(versions, 'ru'), 'tg');
    assert.equal(
        previewLocale(
            { tg: { title: '' }, ru: { title: '' }, en: { title: '' } },
            'ru',
        ),
        'ru',
    );
});

test('any filled translation counts, whitespace does not', () => {
    assert.equal(hasAnyTranslation({ tg: '', ru: '  ', en: 'Title' }), true);
    assert.equal(hasAnyTranslation({ tg: ' ', ru: '', en: '' }), false);
});

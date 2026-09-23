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
    const checks = languageChecks({ tg: 100, ru: 0, en: 0 }, only('Сарлавҳа'));
    const [anyVersion, tg, ru] = checks;

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
    // English is optional: an untouched English version is not a check.
    assert.equal(checks.length, 3);
});

test('a started English version is reported as optional', () => {
    const [, , , en] = languageChecks(
        { tg: 100, ru: 100, en: 50 },
        { tg: 'Сарлавҳа', ru: 'Заголовок', en: 'Title' },
    );

    assert.equal(en.label, 'Английская версия заполнена (необязательно)');
    assert.equal(en.ok, false);
    assert.equal(en.blocking, undefined);
    assert.equal(en.detail, 'Заполнена на 50%.');
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

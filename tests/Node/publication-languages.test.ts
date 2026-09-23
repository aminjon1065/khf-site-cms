import assert from 'node:assert/strict';
import test from 'node:test';
import {
    hasAnyTranslation,
    hasRichText,
    languageChecks,
    missingPart,
    missingVersionNotice,
    previewLocale,
} from '../../resources/js/lib/publication-languages.ts';

const only = (title: string) => ({
    tg: { title, hasText: true },
    ru: { title: '', hasText: false },
    en: { title: '', hasText: false },
});

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
        {
            tg: { title: 'Сарлавҳа', hasText: true },
            ru: { title: 'Заголовок', hasText: true },
            en: { title: 'Title', hasText: true },
        },
    );

    assert.equal(en.label, 'Английская версия заполнена (необязательно)');
    assert.equal(en.ok, false);
    assert.equal(en.blocking, undefined);
    assert.equal(en.detail, 'Заполнена на 50%.');
});

test('publication is blocked until some language version is complete', () => {
    const [anyVersion, , ru] = languageChecks(
        { tg: 0, ru: 60, en: 0 },
        {
            tg: { title: '', hasText: false },
            ru: { title: 'Заголовок', hasText: true },
            en: { title: '', hasText: false },
        },
    );

    assert.equal(anyVersion.ok, false);
    assert.equal(anyVersion.blocking, true);
    assert.equal(ru.detail, 'Заполнена на 60%.');
});

test('a title without text keeps the version off the site', () => {
    const [, tg, ru] = languageChecks(
        { tg: 100, ru: 50, en: 0 },
        {
            tg: { title: 'Сарлавҳа', hasText: true },
            ru: { title: 'Заголовок', hasText: false },
            en: { title: '', hasText: false },
        },
    );

    assert.equal(tg.detail, null);
    assert.equal(
        ru.detail,
        'Нет текста — на русской версии сайта материал не появится.',
    );
    assert.equal(
        missingVersionNotice('ru', 'заголовка', 'text'),
        'Для РУ нет текста — на русской версии сайта материал не появится.',
    );
});

test('an instruction without steps or text says so', () => {
    const [, , ru] = languageChecks(
        { tg: 100, ru: 67, en: 0 },
        {
            tg: { title: 'Заминҷунбӣ', hasText: true },
            ru: { title: 'Землетрясение', hasText: false },
            en: { title: '', hasText: false },
        },
        'названия',
        'ни шагов, ни текста',
    );

    assert.equal(
        ru.detail,
        'Нет ни шагов, ни текста — на русской версии сайта материал не появится.',
    );
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

test('a version needs its title, and its text when the type has one', () => {
    assert.equal(missingPart({ title: ' ', hasText: true }), 'title');
    assert.equal(missingPart({ title: 'Заголовок', hasText: false }), 'text');
    assert.equal(missingPart({ title: 'Заголовок', hasText: true }), null);
    // Documents appear by name alone.
    assert.equal(missingPart({ title: 'Қарор' }), null);
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

test('the preview prefers a language that appears on the site', () => {
    const versions = {
        tg: { title: 'Сарлавҳа', hasText: true },
        ru: { title: 'Заголовок', hasText: false },
        en: { title: '', hasText: false },
    };

    assert.equal(previewLocale(versions, 'en'), 'tg');
    // The language being edited opens even without its text: the notice says
    // what is missing.
    assert.equal(previewLocale(versions, 'ru'), 'ru');
});

test('any filled translation counts, whitespace does not', () => {
    assert.equal(hasAnyTranslation({ tg: '', ru: '  ', en: 'Title' }), true);
    assert.equal(hasAnyTranslation({ tg: ' ', ru: '', en: '' }), false);
});

test('an emptied editor is not a text', () => {
    assert.equal(hasRichText(''), false);
    assert.equal(hasRichText('<p></p>'), false);
    assert.equal(hasRichText('<p> &nbsp; </p><p><br></p>'), false);
    assert.equal(hasRichText('<p>Текст</p>'), true);
    assert.equal(
        hasRichText('<figure><img src="/a.jpg" alt=""></figure>'),
        true,
    );
});

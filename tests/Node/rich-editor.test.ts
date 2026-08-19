import assert from 'node:assert/strict';
import test from 'node:test';
import {
    cleanPastedHtml,
    countWords,
    htmlHasTable,
    htmlHasYoutube,
    normalizeLinkUrl,
    parseCssColor,
    parseYoutubeUrl,
    readingMinutes,
    stripLastTable,
} from '../../resources/js/ui/rich-editor.ts';

test('accepts https links and adds a protocol to bare domains', () => {
    assert.equal(
        normalizeLinkUrl('https://khf.tj/alerts'),
        'https://khf.tj/alerts',
    );
    assert.equal(normalizeLinkUrl('khf.tj'), 'https://khf.tj/');
    assert.equal(normalizeLinkUrl('/news'), '/news');
    assert.equal(
        normalizeLinkUrl('mailto:press@khf.tj'),
        'mailto:press@khf.tj',
    );
    assert.equal(normalizeLinkUrl(''), '');
    assert.equal(normalizeLinkUrl('javascript:alert(1)'), null);
});

test('accepts only YouTube watch, share and embed URLs', () => {
    assert.equal(
        parseYoutubeUrl('https://www.youtube.com/watch?v=abcdefghijk'),
        'https://www.youtube.com/watch?v=abcdefghijk',
    );
    assert.equal(
        parseYoutubeUrl('https://youtu.be/abcdefghijk'),
        'https://www.youtube.com/watch?v=abcdefghijk',
    );
    assert.equal(
        parseYoutubeUrl('https://www.youtube.com/embed/abcdefghijk'),
        'https://www.youtube.com/watch?v=abcdefghijk',
    );
    assert.equal(parseYoutubeUrl('https://vimeo.com/123'), null);
    assert.equal(parseYoutubeUrl('not a url'), null);
});

test('counts words and rounds reading time for a news article', () => {
    assert.equal(countWords(''), 0);
    assert.equal(countWords('  КЧС   провёл учения  '), 3);
    assert.equal(readingMinutes(0), 0);
    assert.equal(readingMinutes(20), 1);
    assert.equal(readingMinutes(180), 1);
    assert.equal(readingMinutes(360), 2);
});

test('strips Word and Docs paste junk but keeps the paragraph', () => {
    const dirty =
        '<!--StartFragment--><p class="MsoNormal" style="margin:0" lang="RU">Текст</p><o:p></o:p>';

    assert.equal(cleanPastedHtml(dirty), '<p>Текст</p>');
});

test('keeps table fill and width when pasting from Word', () => {
    const dirty =
        '<table class="MsoTable"><tr><td style="background-color:#d7e2ea;width:160px;margin:0">A</td></tr></table>';

    assert.equal(
        cleanPastedHtml(dirty),
        '<table><tr><td style="background-color:#d7e2ea; width:160px">A</td></tr></table>',
    );
});

test('normalises CSS colours to hex', () => {
    assert.equal(parseCssColor('#d7e2ea'), '#d7e2ea');
    assert.equal(parseCssColor('#abc'), '#aabbcc');
    assert.equal(parseCssColor('rgb(215, 226, 234)'), '#d7e2ea');
    assert.equal(parseCssColor('transparent'), null);
    assert.equal(parseCssColor(''), null);
});

test('detects tables and youtube blocks in editor HTML', () => {
    assert.equal(htmlHasTable('<p>Текст</p>'), false);
    assert.equal(
        htmlHasTable('<p>До</p><table><tr><td>A</td></tr></table>'),
        true,
    );
    assert.equal(htmlHasYoutube('<p>Текст</p>'), false);
    assert.equal(
        htmlHasYoutube(
            '<div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk"></iframe></div>',
        ),
        true,
    );
});

test('stripLastTable removes only the last table', () => {
    const html =
        '<p>A</p><table><tr><td>1</td></tr></table><p>B</p><table><tr><td>2</td></tr></table><p>C</p>';

    assert.equal(
        stripLastTable(html),
        '<p>A</p><table><tr><td>1</td></tr></table><p>B</p><p>C</p>',
    );
    assert.equal(stripLastTable('<p>Без таблицы</p>'), '<p>Без таблицы</p>');
});

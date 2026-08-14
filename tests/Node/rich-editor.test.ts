import assert from 'node:assert/strict';
import test from 'node:test';
import {
    cleanPastedHtml,
    countWords,
    normalizeLinkUrl,
    parseYoutubeUrl,
    readingMinutes,
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

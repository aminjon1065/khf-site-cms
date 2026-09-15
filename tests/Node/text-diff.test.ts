import assert from 'node:assert/strict';
import test from 'node:test';
import {
    diffLines,
    fieldChanged,
    normalizeField,
} from '../../resources/js/lib/text-diff.ts';

test('identical text yields a single context segment', () => {
    const diff = diffLines('a\nb\nc', 'a\nb\nc');

    assert.equal(diff.length, 1);
    assert.equal(diff[0].type, 'context');
    assert.deepEqual(diff[0].lines, ['a', 'b', 'c']);
});

test('a pure insertion is one added segment', () => {
    const diff = diffLines('a\nc', 'a\nb\nc');

    assert.deepEqual(
        diff.map((s) => ({ type: s.type, lines: s.lines.join('|') })),
        [
            { type: 'context', lines: 'a' },
            { type: 'added', lines: 'b' },
            { type: 'context', lines: 'c' },
        ],
    );
});

test('a change in the middle produces removed then added', () => {
    const diff = diffLines(
        'заголовок\nтекст\nподпись',
        'заголовок\nТЕКСТ\nподпись',
    );

    assert.deepEqual(
        diff.map((s) => s.type),
        ['context', 'removed', 'added', 'context'],
    );
});

test('distant context collapses into ellipsis with nearby context kept', () => {
    const before = Array.from({ length: 20 }, (_, i) => `line${i}`).join('\n');
    const after = before.replace('line10', 'CHANGED');
    const diff = diffLines(before, after);

    assert.ok(diff.some((s) => s.type === 'removed'));
    assert.ok(diff.some((s) => s.lines.includes('…')));
    // Контекст вокруг изменения сохранён.
    assert.ok(
        diff.some((s) => s.type === 'context' && s.lines.includes('line8')),
    );
});

test('empty before is fully added', () => {
    const diff = diffLines('', 'новый\nтекст');

    assert.deepEqual(diff, [{ type: 'added', lines: ['новый', 'текст'] }]);
});

test('fieldChanged and normalizeField treat null, undefined and empty string alike', () => {
    assert.equal(normalizeField(null), '');
    assert.equal(normalizeField(undefined), '');
    assert.equal(fieldChanged('', null), false);
    assert.equal(fieldChanged('a', null), true);
    assert.equal(fieldChanged({ a: 1 }, { a: 1 }), false);
});

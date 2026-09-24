import assert from 'node:assert/strict';
import test from 'node:test';
import {
    CMS_BUNDLE_BUDGETS,
    evaluateCmsBundle,
    staticImportsOf,
} from '../../resources/js/lib/bundle-report.mjs';

test('accepts a CMS bundle inside every budget', () => {
    const report = evaluateCmsBundle(
        [
            {
                path: 'assets/app.js',
                extension: '.js',
                bytes: CMS_BUNDLE_BUDGETS.entryBytes,
            },
            {
                path: 'assets/app.css',
                extension: '.css',
                bytes: CMS_BUNDLE_BUDGETS.largestCssBytes,
            },
        ],
        'assets/app.js',
    );

    assert.deepEqual(report.violations, []);
});

test('reports the exact CMS bundle budget that regressed', () => {
    const report = evaluateCmsBundle(
        [
            {
                path: 'assets/app.js',
                extension: '.js',
                bytes: CMS_BUNDLE_BUDGETS.entryBytes + 1,
            },
        ],
        'assets/app.js',
    );

    assert.equal(report.violations.length, 1);
    assert.match(report.violations[0], /^entryBytes:/);
});

test('counts the chunks the entry imports statically toward the initial load', () => {
    const report = evaluateCmsBundle(
        [
            { path: 'assets/app.js', extension: '.js', bytes: 100 * 1024 },
            {
                path: 'assets/framework.js',
                extension: '.js',
                bytes: CMS_BUNDLE_BUDGETS.initialJavaScriptBytes,
            },
            { path: 'assets/page.js', extension: '.js', bytes: 50 * 1024 },
        ],
        'assets/app.js',
        ['assets/app.js', 'assets/framework.js'],
    );

    assert.equal(
        report.metrics.initialJavaScriptBytes,
        100 * 1024 + CMS_BUNDLE_BUDGETS.initialJavaScriptBytes,
    );
    assert.deepEqual(
        report.violations.map((violation) => violation.split(':')[0]),
        ['initialJavaScriptBytes'],
    );
});

test('follows static imports through the manifest, not dynamic ones', () => {
    const manifest = {
        'resources/js/app.tsx': {
            file: 'assets/app.js',
            imports: ['_framework.js', '_icon.js'],
            dynamicImports: ['resources/js/pages/news/index.tsx'],
        },
        '_framework.js': {
            file: 'assets/framework.js',
            imports: ['_runtime.js'],
        },
        '_icon.js': { file: 'assets/icon.js', imports: ['_runtime.js'] },
        '_runtime.js': { file: 'assets/runtime.js' },
        'resources/js/pages/news/index.tsx': {
            file: 'assets/index.js',
            imports: ['_framework.js'],
        },
    };

    assert.deepEqual(
        [...staticImportsOf(manifest, 'resources/js/app.tsx')].toSorted(),
        ['_framework.js', '_icon.js', '_runtime.js'],
    );
});

import assert from 'node:assert/strict';
import test from 'node:test';
import {
    CMS_BUNDLE_BUDGETS,
    evaluateCmsBundle,
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

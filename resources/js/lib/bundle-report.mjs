import { mkdir, readFile, stat, writeFile } from 'node:fs/promises';
import { dirname, extname, join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

// Перкалибровка 2026-09-20 (audit J-11): пока CI стоял красным до шага
// bundle:report, админ-бандл молча вырос (entry 229.8 KiB, крупнейший JS
// 537.2 KiB — редактор TipTap, CSS 130.3 KiB). Бюджеты подняты к факту
// с небольшим запасом, чтобы гейт снова ловил рост, а не историю;
// план по сокращению крупного чанка — отдельно.
//
// 2026-09-24: Rolldown вклеил рантайм (React, Inertia) в entry — теперь он
// отдельной группой `framework` (vite.config.ts), entry снова 233 KiB.
// Чтобы перекладывание кода между чанками не прятало рост, добавлен бюджет
// начальной загрузки: entry со всеми статическими импортами — 542.8 KiB.
// Редактор (566.6 KiB: меню «/», галереи, блоки) и CSS (161.6 KiB: стили
// редактора wp-*/re-*) выросли вместе с редактором «как в WordPress»;
// бюджеты подняты к факту с тем же небольшим запасом.
export const CMS_BUNDLE_BUDGETS = {
    entryBytes: 240 * 1024,
    initialJavaScriptBytes: 560 * 1024,
    largestJavaScriptBytes: 580 * 1024,
    largestCssBytes: 166 * 1024,
    totalJavaScriptBytes: 1800 * 1024,
};

/**
 * @param initialFiles chunks the browser loads before the first page renders:
 *   the entry and everything it imports statically.
 */
export function evaluateCmsBundle(
    files,
    entryFile,
    initialFiles = [entryFile],
) {
    const javascript = files.filter((file) => file.extension === '.js');
    const css = files.filter((file) => file.extension === '.css');
    const entry = files.find((file) => file.path === entryFile);
    const largestJavaScript = javascript.toSorted(
        (left, right) => right.bytes - left.bytes,
    )[0];
    const largestCss = css.toSorted(
        (left, right) => right.bytes - left.bytes,
    )[0];
    const metrics = {
        entryBytes: entry?.bytes ?? 0,
        initialJavaScriptBytes: javascript
            .filter((file) => initialFiles.includes(file.path))
            .reduce((total, file) => total + file.bytes, 0),
        largestJavaScriptBytes: largestJavaScript?.bytes ?? 0,
        largestCssBytes: largestCss?.bytes ?? 0,
        totalJavaScriptBytes: javascript.reduce(
            (total, file) => total + file.bytes,
            0,
        ),
    };
    const violations = Object.entries(CMS_BUNDLE_BUDGETS)
        .filter(([metric, budget]) => metrics[metric] > budget)
        .map(
            ([metric, budget]) =>
                `${metric}: ${metrics[metric]} bytes exceeds ${budget} bytes`,
        );

    return {
        budgets: CMS_BUNDLE_BUDGETS,
        metrics,
        violations,
        largest: {
            javascript: largestJavaScript ?? null,
            css: largestCss ?? null,
        },
    };
}

/**
 * Manifest keys of the chunks `key` imports statically, transitively — what
 * the browser has to load together with it (Laravel's @vite preloads them).
 */
export function staticImportsOf(manifest, key, found = new Set()) {
    for (const imported of manifest[key]?.imports ?? []) {
        if (!found.has(imported)) {
            found.add(imported);
            staticImportsOf(manifest, imported, found);
        }
    }

    return found;
}

async function createCmsBundleReport(root) {
    const manifestPath = join(root, 'public/build/manifest.json');
    const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
    const assetPaths = [
        ...new Set(
            Object.values(manifest).flatMap((entry) => [
                entry.file,
                ...(entry.css ?? []),
            ]),
        ),
    ].filter((path) => ['.js', '.css'].includes(extname(path)));
    const files = await Promise.all(
        assetPaths.map(async (path) => {
            const absolutePath = join(root, 'public/build', path);
            const contents = await readFile(absolutePath);
            const details = await stat(absolutePath);

            return {
                path,
                extension: extname(path),
                bytes: details.size,
                gzipBytes: gzipSync(contents).byteLength,
            };
        }),
    );
    const entryFile = manifest['resources/js/app.tsx']?.file;

    if (!entryFile) {
        throw new Error(
            'CMS Vite manifest does not contain resources/js/app.tsx.',
        );
    }

    const initialFiles = [
        entryFile,
        ...[...staticImportsOf(manifest, 'resources/js/app.tsx')].map(
            (key) => manifest[key].file,
        ),
    ];

    return {
        generatedAt: new Date().toISOString(),
        entryFile,
        initialFiles,
        files: files.toSorted((left, right) => right.bytes - left.bytes),
        ...evaluateCmsBundle(files, entryFile, initialFiles),
    };
}

function formatBytes(bytes) {
    return `${(bytes / 1024).toFixed(1)} KiB`;
}

function markdown(report) {
    const rows = report.files
        .slice(0, 15)
        .map(
            (file) =>
                `| \`${file.path}\` | ${formatBytes(file.bytes)} | ${formatBytes(file.gzipBytes)} |`,
        )
        .join('\n');

    return `# CMS bundle report

Generated: ${report.generatedAt}

| Budget | Current | Limit |
| --- | ---: | ---: |
| Entry JavaScript | ${formatBytes(report.metrics.entryBytes)} | ${formatBytes(report.budgets.entryBytes)} |
| Initial JavaScript (entry + static imports) | ${formatBytes(report.metrics.initialJavaScriptBytes)} | ${formatBytes(report.budgets.initialJavaScriptBytes)} |
| Largest JavaScript chunk | ${formatBytes(report.metrics.largestJavaScriptBytes)} | ${formatBytes(report.budgets.largestJavaScriptBytes)} |
| Largest CSS asset | ${formatBytes(report.metrics.largestCssBytes)} | ${formatBytes(report.budgets.largestCssBytes)} |
| Total JavaScript | ${formatBytes(report.metrics.totalJavaScriptBytes)} | ${formatBytes(report.budgets.totalJavaScriptBytes)} |

## Largest assets

| Asset | Raw | Gzip |
| --- | ---: | ---: |
${rows}
`;
}

async function main() {
    const currentFile = fileURLToPath(import.meta.url);
    const root = resolve(dirname(currentFile), '../../..');
    const report = await createCmsBundleReport(root);
    const outputDirectory = join(root, 'bundle-reports');

    await mkdir(outputDirectory, { recursive: true });
    await Promise.all([
        writeFile(
            join(outputDirectory, 'cms.json'),
            `${JSON.stringify(report, null, 2)}\n`,
        ),
        writeFile(join(outputDirectory, 'cms.md'), markdown(report)),
    ]);

    console.log(
        `CMS bundle: entry ${formatBytes(report.metrics.entryBytes)}, initial JS ${formatBytes(report.metrics.initialJavaScriptBytes)}, largest JS ${formatBytes(report.metrics.largestJavaScriptBytes)}, total JS ${formatBytes(report.metrics.totalJavaScriptBytes)}.`,
    );

    if (report.violations.length > 0) {
        throw new Error(
            `CMS bundle budgets failed:\n${report.violations.join('\n')}`,
        );
    }
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    await main();
}

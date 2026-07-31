import { mkdir, readFile, stat, writeFile } from 'node:fs/promises';
import { dirname, extname, join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

export const CMS_BUNDLE_BUDGETS = {
    entryBytes: 220 * 1024,
    largestJavaScriptBytes: 500 * 1024,
    largestCssBytes: 130 * 1024,
    totalJavaScriptBytes: 1650 * 1024,
};

export function evaluateCmsBundle(files, entryFile) {
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

    return {
        generatedAt: new Date().toISOString(),
        entryFile,
        files: files.toSorted((left, right) => right.bytes - left.bytes),
        ...evaluateCmsBundle(files, entryFile),
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
        `CMS bundle: entry ${formatBytes(report.metrics.entryBytes)}, largest JS ${formatBytes(report.metrics.largestJavaScriptBytes)}, total JS ${formatBytes(report.metrics.totalJavaScriptBytes)}.`,
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

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Ctrl+S во всех редакторах сохраняет черновик и оставляет на месте, как в
// WordPress. Страница и объявление раньше возвращали в список.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);

function removeTestDrafts(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `
            App\\Models\\Page::withTrashed()->where('title->ru', 'like', 'E2E-ctrl-s%')->get()->each->forceDelete();
            App\\Models\\Announcement::withTrashed()->where('title->ru', 'like', 'E2E-ctrl-s%')->get()->each->forceDelete();
            `,
        ],
        { cwd: PROJECT_ROOT },
    );
}

test.beforeEach(removeTestDrafts);
test.afterEach(removeTestDrafts);

for (const { path: list, field } of [
    { path: 'pages', field: 'Заголовок страницы' },
    { path: 'announcements', field: 'Заголовок объявления' },
]) {
    test(`Ctrl+S keeps the ${list} editor open`, async ({ page }) => {
        await page.goto(`/${list}/create`);
        await page
            .getByRole('textbox', { name: field })
            .fill(`E2E-ctrl-s: черновик (${list})`);

        await page.keyboard.press('Control+s');

        await expect(page).toHaveURL(new RegExp(`/${list}/\\d+/edit$`));
        await expect(page.getByRole('textbox', { name: field })).toHaveValue(
            `E2E-ctrl-s: черновик (${list})`,
        );
    });
}

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// «Восстановить» в истории версий загружает версию в редактор и ничего не
// сохраняет: на сайт она попадает обычным сохранением — с проверками,
// очисткой текста и согласованием. Раньше восстановление писало ревизию прямо
// в материал, в обход всего этого.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);

function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
    }).toString();
}

function removeTestNews(): void {
    tinker(`
        $ids = App\\Models\\News::withTrashed()->where('title->ru', 'like', 'E2E-restore%')->pluck('id');
        App\\Models\\EditorialRevision::query()->where('content_type', 'news')->whereIn('content_id', $ids)->delete();
        App\\Models\\News::withTrashed()->whereIn('id', $ids)->get()->each->forceDelete();
    `);
}

test.beforeEach(removeTestNews);
test.afterEach(removeTestNews);

test('restoring a version fills the editor and leaves the material as it is', async ({
    page,
}) => {
    const title = page.getByRole('textbox', { name: 'Заголовок новости' });

    await page.goto('/news/create');
    await title.fill('E2E-restore: первая версия');
    await page.keyboard.press('Control+s');
    await expect(page).toHaveURL(/\/news\/\d+\/edit$/);
    const id = page.url().match(/\/news\/(\d+)\/edit$/)?.[1];

    await title.fill('E2E-restore: вторая версия');
    await page.keyboard.press('Control+s');
    await expect
        .poll(() =>
            tinker(
                `echo App\\Models\\News::find(${id})->getTranslation('title', 'ru');`,
            ).trim(),
        )
        .toBe('E2E-restore: вторая версия');

    await page.getByRole('button', { name: 'История', exact: true }).click();
    const history = page.getByRole('region', { name: 'История версий' });
    // Newest first: the first manual save is the last item.
    await history
        .getByRole('listitem')
        .last()
        .getByRole('button', { name: 'Восстановить' })
        .click();

    await expect(page.getByText('Версия загружена в редактор')).toBeVisible();
    await expect(title).toHaveValue('E2E-restore: первая версия');

    expect(
        tinker(
            `echo App\\Models\\News::find(${id})->getTranslation('title', 'ru');`,
        ).trim(),
    ).toBe('E2E-restore: вторая версия');
});

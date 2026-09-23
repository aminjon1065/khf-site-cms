import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Массовые действия в списке, как в WordPress: отметить строки и опубликовать
// разом. Каждая новость проходит ту же проверку, что и при публикации по одной:
// готовые публикуются, неготовая остаётся черновиком, а CMS объясняет почему.
// Входит редактор: публиковать новости ему можно, удалять — нет.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const PREFIX = 'E2E-массовые';

function tinker(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
    });
}

function removeTestNews(): void {
    tinker(
        `App\\Models\\News::withTrashed()->where('title->ru', 'like', '${PREFIX}%')->get()->each->forceDelete();`,
    );
}

test.beforeEach(() => {
    removeTestNews();
    tinker(`
        foreach ([1, 2] as $n) {
            App\\Models\\News::create([
                'title' => ['ru' => "${PREFIX}: готовая {$n}", 'tg' => "${PREFIX}: тайёр {$n}"],
                'summary' => ['ru' => 'Кратко', 'tg' => 'Мухтасар'],
                'body' => ['ru' => '<p>Текст</p>', 'tg' => '<p>Матн</p>'],
                'status' => 'draft',
            ]);
        }
        App\\Models\\News::create(['title' => ['ru' => '${PREFIX}: без текста'], 'status' => 'draft']);
    `);
});

test.afterEach(removeTestNews);

test('editor publishes several news at once and sees what was skipped', async ({
    page,
}) => {
    await page.goto(`/news?search=${encodeURIComponent(PREFIX)}`);
    await expect(page.getByText(`${PREFIX}: без текста`)).toBeVisible();

    // Флажок — скрытый input внутри подписи: кликаем как пользователь.
    await page
        .getByRole('checkbox', { name: 'Выбрать все' })
        .locator('..')
        .click();
    await expect(page.getByText('Выбрано: 3')).toBeVisible();
    await expect(page.getByRole('button', { name: 'В корзину' })).toHaveCount(
        0,
    );

    await page
        .getByRole('button', { name: 'Опубликовать', exact: true })
        .click();
    const confirm = page.getByRole('dialog', {
        name: 'Опубликовать выбранные материалы (3)?',
    });
    await confirm.getByRole('button', { name: 'Опубликовать' }).click();

    const report = page.getByRole('dialog', { name: 'Опубликовано: 2 из 3.' });
    await expect(report).toContainText(
        `${PREFIX}: без текста — Перед публикацией исправьте: Хотя бы одна языковая версия заполнена.`,
    );
    await report.getByRole('button', { name: 'Понятно' }).click();

    const rows = page.getByRole('row');
    await expect(
        rows.filter({ hasText: `${PREFIX}: готовая 1` }),
    ).toContainText('Опубликовано');
    await expect(
        rows.filter({ hasText: `${PREFIX}: без текста` }),
    ).toContainText('Черновик');
    await expect(page.getByText(/^Выбрано:/)).toHaveCount(0);
});

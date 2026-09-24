import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Форма проекта в редакторе, как у новости: название, описание и текст на
// холсте, цели и этапы под текстом, параметры и обложка в панели справа.
// Ctrl+S сохраняет черновик и оставляет в редакторе — всё введённое на месте.

const DIRNAME = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(DIRNAME, '..', '..');
const TITLE = 'E2E-проект: модернизация оповещения';

function removeTestProjects(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\Project::withTrashed()->where('title->ru', 'like', 'E2E-проект%')->get()->each->forceDelete();`,
        ],
        { cwd: PROJECT_ROOT },
    );
}

test.beforeEach(removeTestProjects);
test.afterEach(removeTestProjects);

test('editor fills a project on the canvas and in the panel, then saves with Ctrl+S', async ({
    page,
}) => {
    await page.goto('/projects/create');

    await page.getByRole('textbox', { name: 'Название проекта' }).fill(TITLE);
    await page
        .getByRole('textbox', { name: 'Краткое описание проекта' })
        .fill('Сирены и СМС-оповещение в Хатлонской области.');

    await page.getByRole('button', { name: 'Добавить цель' }).click();
    await page
        .getByRole('textbox', { name: 'Цель 1' })
        .fill('Оповестить 95% населения за 10 минут.');

    await page.getByRole('button', { name: 'Добавить этап' }).click();
    await page
        .getByRole('textbox', { name: 'Этап 1: когда' })
        .fill('Июнь 2026');
    await page
        .getByRole('textbox', { name: 'Этап 1: что сделано или запланировано' })
        .fill('Установлены первые 40 сирен.');

    const panel = page.getByRole('complementary', {
        name: 'Настройки проекта',
    });
    await panel.getByRole('textbox', { name: 'Сроки' }).fill('2026–2030');

    const cover = panel.locator('section', {
        has: page.getByRole('heading', { name: 'Обложка' }),
    });
    await cover
        .locator('input[type="file"]')
        .setInputFiles(path.join(DIRNAME, 'fixtures', 'cover.png'));
    await expect(cover.getByRole('img', { name: 'Обложка' })).toBeVisible();

    await page.keyboard.press('Control+s');
    await expect(page).toHaveURL(/\/projects\/\d+\/edit$/);

    await page.reload();
    await expect(
        page.getByRole('textbox', { name: 'Название проекта' }),
    ).toHaveValue(TITLE);
    await expect(page.getByRole('textbox', { name: 'Цель 1' })).toHaveValue(
        'Оповестить 95% населения за 10 минут.',
    );
    await expect(
        page.getByRole('textbox', { name: 'Этап 1: когда' }),
    ).toHaveValue('Июнь 2026');
    await expect(panel.getByRole('textbox', { name: 'Сроки' })).toHaveValue(
        '2026–2030',
    );
    await expect(cover.getByRole('img', { name: 'Обложка' })).toBeVisible();
});

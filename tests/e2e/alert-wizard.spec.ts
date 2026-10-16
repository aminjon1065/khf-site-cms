import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// «Сохранить черновик» в мастере предупреждения раньше возвращал оператора в
// список, сбрасывая шаг, а внизу обещал автосохранение, которого в мастере
// нет. Теперь сохранение оставляет на том же шаге и честно говорит, когда
// черновик сохранён.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);

function removeTestAlerts(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\Alert::withTrashed()->where('internal_title', 'like', 'E2E-wizard%')->get()->each->forceDelete();`,
        ],
        { cwd: PROJECT_ROOT },
    );
}

test.beforeEach(removeTestAlerts);
test.afterEach(removeTestAlerts);

test('saving a draft keeps the operator on the current wizard step', async ({
    page,
}) => {
    await page.goto('/alerts/create');
    await expect(
        page.getByText('Сохраняется кнопкой «Сохранить черновик»'),
    ).toBeVisible();
    await expect(
        page.getByText('Черновик сохраняется автоматически'),
    ).toHaveCount(0);

    await page.locator('select').first().selectOption('mudflow');
    await page
        .getByPlaceholder(/Селевая опасность/)
        .fill('E2E-wizard: черновик');
    await page.getByRole('button', { name: 'Далее' }).click();
    await page.getByLabel('Вся страна').check();

    await page.getByRole('button', { name: 'Сохранить черновик' }).click();

    await expect(page).toHaveURL(/\/alerts\/\d+\/edit$/);
    await expect(page.getByText('Этап 2 из 5')).toBeVisible();
    await expect(page.getByText(/^Сохранено в \d{2}:\d{2}$/)).toBeVisible();
    await expect(page.getByLabel('Вся страна')).toBeChecked();

    // Step 4 no longer shows upload areas that did nothing.
    await page.getByRole('button', { name: 'Далее' }).click();
    await page.getByRole('button', { name: 'Далее' }).click();
    await expect(page.getByText('Этап 4 из 5')).toBeVisible();
    await expect(
        page.getByText('Перетащите файл или выберите из медиатеки'),
    ).toHaveCount(0);
    await expect(page.getByText('Экстренные номера')).toHaveCount(0);
});

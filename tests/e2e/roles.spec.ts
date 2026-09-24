import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import type { Browser, Page } from '@playwright/test';
import { logIn } from './fixtures/login';

// Три роли из коробки, остальные администратор собирает сам: отмечает права
// по разделам. «Просмотр» раздела ставится вместе с любым другим правом, а
// право публиковать тут же предупреждает о входе с кодом (2FA).

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const LABEL = 'E2E-Дежурный';

function removeTestRoles(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\Role::query()->where('label', 'like', '${LABEL}%')->get()->each->delete();`,
        ],
        { cwd: PROJECT_ROOT },
    );
}

async function asAdministrator(browser: Browser): Promise<Page> {
    const context = await browser.newContext({
        storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    await logIn(page, 'admin@khf.tj');

    return page;
}

/** The Checkbox input is hidden inside its label: click it as a person does. */
function right(page: Page, name: string) {
    return page.getByRole('checkbox', { name, exact: true });
}

test.beforeEach(removeTestRoles);
test.afterEach(removeTestRoles);

test('the administrator builds a role, changes it and removes it', async ({
    browser,
}) => {
    test.setTimeout(90_000);
    const page = await asAdministrator(browser);

    await page.goto('/roles');
    await expect(
        page.getByRole('heading', { name: 'Главный редактор' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Редактор', exact: true }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Добавить роль' }).click();
    await page.getByLabel('Название').fill(LABEL);

    await right(page, 'Предупреждения: публикация').locator('..').click();
    await expect(right(page, 'Предупреждения: просмотр')).toBeChecked();
    await expect(page.getByText('будут входить с кодом из')).toBeVisible();

    // Accounts and settings stay with the administrator.
    await expect(right(page, 'Пользователи и роли: правка')).toHaveCount(0);

    await page.getByRole('button', { name: 'Сохранить' }).first().click();
    await expect(page).toHaveURL(/\/roles$/);
    await expect(page.getByRole('heading', { name: LABEL })).toBeVisible();

    // Without seeing a section, no other right in it stays.
    await page.getByRole('link', { name: `Изменить роль «${LABEL}»` }).click();
    await right(page, 'Предупреждения: просмотр').locator('..').click();
    await expect(right(page, 'Предупреждения: публикация')).not.toBeChecked();
    await expect(page.getByText('будут входить с кодом из')).toHaveCount(0);
    await page.getByRole('button', { name: 'Сохранить' }).first().click();
    await expect(page).toHaveURL(/\/roles$/);

    await page.getByRole('link', { name: `Изменить роль «${LABEL}»` }).click();
    await page.getByRole('button', { name: 'Удалить роль' }).click();
    await page.getByRole('button', { name: 'Удалить', exact: true }).click();
    await expect(page).toHaveURL(/\/roles$/);
    await expect(page.getByRole('heading', { name: LABEL })).toHaveCount(0);

    await page.context().close();
});

test('an account is limited to a region only once a region is chosen', async ({
    browser,
}) => {
    // Signing in as the same person again waits for the next 2FA code.
    test.setTimeout(90_000);
    const page = await asAdministrator(browser);

    await page.goto('/users/create');
    const limit = page.getByRole('checkbox', {
        name: 'Только свои материалы и предупреждения этого региона',
    });
    await expect(limit).toBeDisabled();

    await page.getByLabel('Регион', { exact: true }).selectOption({ index: 1 });
    await expect(limit).toBeEnabled();

    // The administrator works with everything: no limit to offer.
    await page.getByLabel('Роль', { exact: true }).selectOption('admin');
    await expect(limit).toHaveCount(0);

    await page.context().close();
});

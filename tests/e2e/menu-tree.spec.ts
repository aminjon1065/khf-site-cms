import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const DIRNAME = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(DIRNAME, '..', '..');

const GROUP = 'E2E-меню-группа';
const CHILD = 'E2E-меню-подпункт';
const CHILD_URL = '/e2e-menu-child';
const E2E_EMAIL = 'e2e.menu@khf.tj';

function artisan(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
    });
}

test.use({ storageState: { cookies: [], origins: [] } });

test.beforeAll(() => {
    artisan(`
        $row = App\\Models\\Setting::query()->where('group', 'security')->where('key', 'require_2fa')->first();
        $row->value = false;
        $row->save();
        $user = App\\Models\\User::query()->firstOrNew(['email' => '${E2E_EMAIL}']);
        $user->name = 'E2E Menu';
        $user->password = 'password';
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();
        $user->syncRoles(['admin']);
    `);
});

test.afterAll(() => {
    artisan(`
        App\\Models\\MenuItem::query()->where('url', '${CHILD_URL}')->orWhere('label->ru', '${CHILD}')->delete();
        App\\Models\\MenuItem::query()->where('label->ru', '${GROUP}')->delete();
        App\\Models\\User::query()->where('email', '${E2E_EMAIL}')->delete();
        $row = App\\Models\\Setting::query()->where('group', 'security')->where('key', 'require_2fa')->first();
        $row->value = true;
        $row->save();
    `);
});

test('admin can nest a child item and see it after save', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(E2E_EMAIL);
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Войти' }).click();
    await expect(page).toHaveURL(/\/dashboard/);

    await page.goto('/menu');
    await expect(
        page.getByRole('heading', { name: 'Меню сайта' }),
    ).toBeVisible();

    const main = page.locator('[data-location="main"]');
    await main.getByRole('button', { name: 'Пункт', exact: true }).click();
    await main.getByLabel('Заголовок на русском').last().fill(GROUP);
    await main.getByLabel('Заголовок на таджикском').last().fill(GROUP);

    await main
        .getByRole('button', { name: 'Подпункт', exact: true })
        .last()
        .click();
    await main.getByLabel('Подпункт на русском').last().fill(CHILD);
    await main.getByLabel('Подпункт на таджикском').last().fill(CHILD);
    await main.getByPlaceholder('/news').last().fill(CHILD_URL);

    await page.getByRole('button', { name: 'Сохранить меню' }).click();
    await expect(page.getByText('Меню сайта сохранено')).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Подпункт на русском').last()).toHaveValue(
        CHILD,
    );

    const stored = execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `echo 'NESTED='.App\\Models\\MenuItem::query()->where('url', '${CHILD_URL}')->whereNotNull('parent_id')->count();`,
        ],
        { cwd: PROJECT_ROOT, encoding: 'utf8' },
    );
    expect(stored).toContain('NESTED=1');

    const response = await page.request.get(
        `/api/v1/menu?locale=ru&cb=${Date.now()}`,
    );
    expect(response.ok()).toBeTruthy();
    const body = (await response.json()) as {
        data: {
            main: {
                label: string;
                children: { label: string; url: string | null }[];
            }[];
        };
    };
    const group = body.data.main.find((item) => item.label === GROUP);

    expect(group?.children.some((child) => child.url === CHILD_URL)).toBe(true);
});

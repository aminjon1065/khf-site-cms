import { test, expect } from '@playwright/test';

/**
 * Паритет «видимость меню ↔ права»: то, что сайдбар показывает роли,
 * обязано открываться. Регрессия охраняет скрытие пунктов по правам
 * (navItemAllowed) — если новый пункт меню забыли закрыть permission,
 * тест упадёт на 403 вместо пользователя.
 *
 * Роли выбраны из числа не требующих 2FA (RequireTwoFactor), чтобы
 * логин проходил без TOTP; admin/chief_editor/approver остаются
 * вне e2e до появления стратегии генерации кодов.
 */
const ROLES = [
    { label: 'editor', email: 'd.sattorov@khf.tj' },
    { label: 'translator', email: 'j.kholov@khf.tj' },
    { label: 'regional_editor', email: 'n.odinaeva@khf.tj' },
];

test('каждый видимый пункт сайдбара открывается без 403', async ({
    browser,
}) => {
    // Три последовательных логина плюс обход всех пунктов на роль —
    // на медленном стенде выходит за дефолтные 30 секунд.
    test.setTimeout(240_000);

    for (const role of ROLES) {
        // Явно пустое состояние: контексты из browser.newContext()
        // наследуют storageState проекта (сессия редактора из auth.setup),
        // и тогда /login редиректит на /dashboard до логина роли.
        const context = await browser.newContext({
            storageState: { cookies: [], origins: [] },
        });
        const page = await context.newPage();

        await page.goto('/login');
        await page.locator('#email').fill(role.email);
        await page.locator('#password').fill('password');
        await page.getByRole('button', { name: 'Войти' }).click();
        await expect(page).toHaveURL(/dashboard/);

        const links = page.locator('nav.ui-sidebar-nav a');
        const count = await links.count();
        expect(count, `${role.label}: сайдбар пуст`).toBeGreaterThan(0);

        const hrefs = new Set<string>();

        for (let i = 0; i < count; i += 1) {
            const href = await links.nth(i).getAttribute('href');

            if (href) {
                hrefs.add(href.split('?')[0]);
            }
        }

        for (const href of hrefs) {
            console.log(`[${role.label}] GET ${href}`);
            const response = await page.request.get(href, {
                timeout: 20_000,
            });

            expect(
                response.status(),
                `${role.label}: ${href} отвечает ${response.status()}`,
            ).toBeLessThan(400);
        }

        await context.close();
    }
});

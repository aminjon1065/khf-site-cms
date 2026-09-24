import { test, expect } from '@playwright/test';
import { logIn } from './fixtures/login';

/**
 * Паритет «видимость меню ↔ права»: то, что сайдбар показывает роли,
 * обязано открываться. Регрессия охраняет скрытие пунктов по правам
 * (navItemAllowed) — если новый пункт меню забыли закрыть permission,
 * тест упадёт на 403 вместо пользователя.
 *
 * Редактор публикует новости и входит с кодом 2FA — его выдаёт
 * fixtures/login.ts из того же секрета, что и сидер стенда.
 */
const ROLES = [
    { label: 'editor', email: 'd.sattorov@khf.tj' },
    // Редактор регионального управления: только свои материалы и
    // предупреждения Согдийской области.
    { label: 'editor, limited to a region', email: 'n.odinaeva@khf.tj' },
    { label: 'chief_editor', email: 'f.nazarov@khf.tj' },
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

        await logIn(page, role.email);

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

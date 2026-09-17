import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

// Эмблема Комитета вместо значка-щита — на входе и в сайдбаре. Битая картинка
// тоже «видима», поэтому проверяется, что файл действительно загрузился.
async function expectLoaded(image: Locator): Promise<void> {
    await expect(image).toBeVisible();
    await expect
        .poll(() =>
            image.evaluate((element: HTMLImageElement) => element.naturalWidth),
        )
        .toBeGreaterThan(0);
}

async function expectFaviconsServed(page: Page): Promise<void> {
    const hrefs = await page
        .locator(
            'link[rel="icon"], link[rel="apple-touch-icon"], link[rel="manifest"]',
        )
        .evaluateAll((links) =>
            links.map((link) => link.getAttribute('href') ?? ''),
        );

    expect(hrefs.length).toBeGreaterThan(0);

    for (const href of hrefs) {
        expect((await page.request.get(href)).ok(), href).toBe(true);
    }
}

test.describe('guest', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login page shows the Committee emblem and serves its favicons', async ({
        page,
    }) => {
        await page.goto('/login');

        await expectLoaded(page.locator('img[src="/logo.webp"]'));
        await expectFaviconsServed(page);
    });
});

test('sidebar shows the Committee emblem', async ({ page }) => {
    await page.goto('/dashboard');

    await expectLoaded(page.locator('.ui-sidebar-brand img[src="/logo.webp"]'));
});

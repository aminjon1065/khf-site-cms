import { expect, test } from '@playwright/test';

test('control panel chrome uses the official KHF steel blue', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const sidebar = page.locator('.ui-sidebar');
    await expect(sidebar).toBeVisible();

    const colors = await page.evaluate(() => {
        const sidebarEl = document.querySelector('.ui-sidebar');
        const create = document.querySelector('.cms-create-trigger');
        const surface = document.querySelector('.ui-opstatus');

        return {
            sidebar: sidebarEl
                ? getComputedStyle(sidebarEl).backgroundColor
                : '',
            create: create ? getComputedStyle(create).backgroundColor : '',
            surface: surface ? getComputedStyle(surface).backgroundColor : '',
            font: getComputedStyle(document.body).fontFamily,
        };
    });

    expect(colors.sidebar).toBe('rgb(28, 28, 50)');
    expect(colors.create).toBe('rgb(65, 97, 128)');
    expect(colors.surface).toBe('rgb(255, 255, 255)');
    expect(colors.font.toLowerCase()).toContain('inter');
    await expect(page.getByText('Коллекции', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Найти в панели' }),
    ).toBeVisible();

    await page.screenshot({
        path: 'test-results/statamic-dashboard.png',
        fullPage: true,
    });

    await page.goto('/news');
    await expect(page.getByRole('heading', { name: /новост/i })).toBeVisible();
    await page.screenshot({
        path: 'test-results/statamic-news.png',
        fullPage: true,
    });
});

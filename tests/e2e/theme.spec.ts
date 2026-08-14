import { expect, test } from '@playwright/test';

test('profile page changes the CMS theme and dashboard status card stays white', async ({
    page,
}) => {
    await page.goto('/profile');
    await expect(
        page.getByRole('heading', { name: 'Тема оформления' }),
    ).toBeVisible();

    await page.getByRole('radio', { name: 'Тёмная' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);

    await page.getByRole('radio', { name: 'Светлая' }).click();
    await expect(page.locator('html')).not.toHaveClass(/dark/);

    await page.goto('/dashboard');
    const status = page.locator('.ui-opstatus');
    await expect(status).toBeVisible();

    const background = await status.evaluate(
        (element) => getComputedStyle(element).backgroundColor,
    );
    expect(background).toBe('rgb(255, 255, 255)');
});

test('search palette stays dark when the CMS theme is dark', async ({
    page,
}) => {
    await page.goto('/profile');
    await page.getByRole('radio', { name: 'Тёмная' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);

    await page.getByRole('button', { name: 'Поиск по CMS' }).click();
    const panel = page.locator('.cms-cmd-panel');
    await expect(panel).toBeVisible();
    await expect(page.getByPlaceholder(/Команда или поиск/)).toBeVisible();

    const colors = await panel.evaluate((element) => {
        const input = element.querySelector('.cms-cmd-input');

        return {
            panel: getComputedStyle(element).backgroundColor,
            text: getComputedStyle(element).color,
            input: input ? getComputedStyle(input).color : '',
        };
    });

    expect(colors.panel).not.toBe('rgb(251, 251, 252)');
    expect(colors.panel).not.toBe('rgb(255, 255, 255)');
    expect(colors.text).not.toBe('rgb(26, 29, 31)');

    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await page.getByRole('radio', { name: 'Светлая' }).click();
});

const DARK_PAGES = [
    '/dashboard',
    '/news',
    '/news/create',
    '/media',
    '/notifications',
] as const;

test('dark theme does not leave light panels on the main CMS screens', async ({
    page,
}) => {
    await page.goto('/profile');
    await page.getByRole('radio', { name: 'Тёмная' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);

    const light = new Set(['rgb(255, 255, 255)', 'rgb(251, 251, 252)']);

    for (const path of DARK_PAGES) {
        await page.goto(path);
        const leftovers = await page
            .locator(
                '.ui-blueprint, .ui-input, .ui-textarea, .ui-select, .ui-metric, .ui-opstatus, .ui-filterbar, .ui-dialog, .ui-drawer, .ui-toast, .news-form-actions, .editorial-form-actions',
            )
            .evaluateAll(
                (elements, forbidden) =>
                    elements
                        .map((element) => ({
                            className: element.className
                                .toString()
                                .slice(0, 80),
                            background:
                                getComputedStyle(element).backgroundColor,
                        }))
                        .filter((entry) =>
                            forbidden.includes(entry.background),
                        ),
                [...light],
            );

        expect(leftovers, path).toEqual([]);
    }

    await page.goto('/profile');
    await page.getByRole('radio', { name: 'Светлая' }).click();
});

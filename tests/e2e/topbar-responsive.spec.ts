import { expect, test } from '@playwright/test';

test.use({ viewport: { width: 390, height: 844 } });

test('mobile topbar stays inside the viewport and keeps its primary actions accessible', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const primaryActions = [
        page.getByRole('button', { name: 'Меню' }),
        page.getByRole('button', { name: 'Поиск по CMS' }),
        page.getByRole('button', { name: 'Создать', exact: true }),
        page.getByRole('button', { name: 'Уведомления' }),
        page.locator('.cms-topbar-profile'),
    ];

    for (const action of primaryActions) {
        await expect(action).toBeVisible();

        const box = await action.boundingBox();

        expect(box?.width).toBeGreaterThanOrEqual(44);
        expect(box?.height).toBeGreaterThanOrEqual(44);
    }

    const viewport = await page.locator('html').evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    const overflowers = await page.locator('body *').evaluateAll((elements) =>
        elements
            .map((element) => {
                const rect = element.getBoundingClientRect();

                return {
                    className: element.className,
                    left: Math.round(rect.left),
                    right: Math.round(rect.right),
                    tag: element.tagName,
                };
            })
            .filter(
                ({ right }) => right > document.documentElement.clientWidth + 1,
            )
            .slice(0, 20),
    );

    expect(
        viewport.scrollWidth,
        `Elements outside the viewport: ${JSON.stringify(overflowers)}`,
    ).toBe(viewport.clientWidth);
});

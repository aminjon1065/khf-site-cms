import { expect, test } from '@playwright/test';

// Контент страницы упирался в max-width 85rem без центрирования: на мониторе
// 1920px справа от него оставалась пустая полоса почти в треть экрана.
// Проверяем, что основная область доходит до правого края, как и топбар.
test.use({ viewport: { width: 1920, height: 1080 } });

test('page content spans the full width next to the sidebar on a wide screen', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const main = page.getByRole('main');
    await expect(main).toBeVisible();

    const mainBox = await main.boundingBox();
    const viewportWidth = await page.evaluate(
        () => document.documentElement.clientWidth,
    );

    expect(mainBox, 'основная область должна иметь размеры').not.toBeNull();
    expect(mainBox!.x + mainBox!.width).toBeGreaterThanOrEqual(
        viewportWidth - 1,
    );
});

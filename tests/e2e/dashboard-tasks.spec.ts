import { expect, test } from '@playwright/test';

test('editor dashboard links to the translation queue instead of an inaccessible approval center', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const main = page.getByRole('main');
    const taskCenter = main.getByRole('link', {
        name: 'Очередь переводов',
    });

    await expect(taskCenter).toBeVisible();
    await expect(
        main.getByRole('link', { name: 'Центр согласования' }),
    ).toHaveCount(0);

    await taskCenter.click();
    await expect(page).toHaveURL(/\/editorial\/translations$/);
    await expect(
        page.getByRole('heading', { name: 'Очередь переводов' }),
    ).toBeVisible();
});

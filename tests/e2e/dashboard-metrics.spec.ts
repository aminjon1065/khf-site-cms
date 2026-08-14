import { expect, test } from '@playwright/test';

test('dashboard metric cards open the matching filtered lists', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const main = page.getByRole('main');

    await main.getByRole('link', { name: /^\d+ активных предупреждения$/ }).click();
    await expect(page).toHaveURL(/\/alerts\?view=active/);
    await expect(
        page.getByRole('heading', { name: 'Предупреждения' }),
    ).toBeVisible();
    await expect(page.getByRole('tab', { name: /Активные/ })).toHaveAttribute(
        'aria-selected',
        'true',
    );

    await page.goto('/dashboard');
    await main.getByRole('link', { name: /^\d+ черновиков$/ }).click();
    await expect(page).toHaveURL(/view=drafts|status=draft/);
    await expect(page.getByRole('heading').first()).toBeVisible();

    await page.goto('/dashboard');
    await main.getByRole('link', { name: /^\d+ на согласовании$/ }).click();
    await expect(page).toHaveURL(/view=review|\/approvals/);
    await expect(page.getByRole('heading').first()).toBeVisible();

    await page.goto('/dashboard');
    await main.getByRole('link', { name: /^\d+ запланировано$/ }).click();
    await expect(page).toHaveURL(/view=scheduled/);
    await expect(page.getByRole('heading').first()).toBeVisible();

    await page.goto('/dashboard');
    await main.getByRole('link', { name: /^\d+ опубликовано за месяц$/ }).click();
    await expect(page).toHaveURL(/view=published|status=published/);
    await expect(page.getByRole('heading').first()).toBeVisible();

    await page.goto('/dashboard');
    await main
        .getByRole('link', { name: /^\d+ незавершённых переводов$/ })
        .click();
    await expect(page).toHaveURL(/\/editorial\/translations$/);
    await expect(
        page.getByRole('heading', { name: 'Очередь переводов' }),
    ).toBeVisible();
});

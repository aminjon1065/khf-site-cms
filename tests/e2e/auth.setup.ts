import { test as setup, expect } from '@playwright/test';

// Logs in once as a seeded, 2FA-exempt editor (UserSeeder: d.sattorov@khf.tj,
// role editor, twoFactor => false) and saves the session for every other
// spec to reuse — see playwright.config.ts for why this specific account.
const AUTH_FILE = 'tests/e2e/.auth/editor.json';

setup('authenticate as a 2FA-exempt editor', async ({ page }) => {
    await page.goto('/login');
    // #password's accessible name collides with the "Показать пароль" toggle
    // button under getByLabel's default substring match — target the inputs
    // by id instead.
    await page.locator('#email').fill('d.sattorov@khf.tj');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Войти' }).click();

    await expect(page).toHaveURL(/\/dashboard/);
    await page.context().storageState({ path: AUTH_FILE });
});

import { test as setup } from '@playwright/test';
import { logIn } from './fixtures/login';

// Logs in once as a seeded editor (UserSeeder: d.sattorov@khf.tj) and saves
// the session for every other spec. Editors publish news, so they sign in
// with a 2FA code: the stand seeds it from DEMO_TWO_FACTOR_SECRET, and
// fixtures/login.ts derives the same code.
const AUTH_FILE = 'tests/e2e/.auth/editor.json';

setup('authenticate as an editor with a 2FA code', async ({ page }) => {
    await logIn(page, 'd.sattorov@khf.tj');
    await page.context().storageState({ path: AUTH_FILE });
});

import { createHmac } from 'node:crypto';
import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Sign-in for browser tests, including the 2FA code: everyone who may publish
 * signs in with one (RequireTwoFactor). The stand seeds each demo account a
 * TOTP secret derived from DEMO_TWO_FACTOR_SECRET and the e-mail
 * (UserSeeder::twoFactorFor); this file derives the same key. Set
 * CMS_E2E_TWO_FACTOR_SECRET to the stand's DEMO_TWO_FACTOR_SECRET.
 */
export const E2E_TWO_FACTOR_SECRET =
    process.env.CMS_E2E_TWO_FACTOR_SECRET ?? 'khf-e2e-stand';

const STEP_SECONDS = 30;

/** RFC 6238 code (SHA-1, 30 s, 6 digits), as Google Authenticator shows it. */
export function totpFor(email: string, at: number = Date.now()): string {
    const key = createHmac('sha1', E2E_TWO_FACTOR_SECRET)
        .update(email)
        .digest();
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(at / 1000 / STEP_SECONDS)));
    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;
    const value = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return value.toString().padStart(6, '0');
}

async function enterCode(page: Page, email: string): Promise<void> {
    const code = totpFor(email);

    for (const [index, digit] of [...code].entries()) {
        await page.getByLabel(`Цифра ${index + 1}`).fill(digit);
    }

    await page.getByRole('button', { name: 'Подтвердить и войти' }).click();
}

/**
 * Signs in as a demo account («password») and gets past the 2FA challenge.
 * Fortify refuses a code already used within its window, so when two tests
 * sign in as the same person back to back the second waits for the next one.
 */
export async function logIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Войти' }).click();
    await page.waitForURL(
        /\/(dashboard|two-factor-challenge|profile\/security)/,
    );

    if (page.url().includes('/profile/security')) {
        throw new Error(
            `${email} должен настроить 2FA: на стенде не задан DEMO_TWO_FACTOR_SECRET или он не совпадает с CMS_E2E_TWO_FACTOR_SECRET.`,
        );
    }

    if (!page.url().includes('two-factor-challenge')) {
        return;
    }

    await enterCode(page, email);

    try {
        await page.waitForURL(/\/dashboard/, { timeout: 5_000 });
    } catch {
        const wait =
            STEP_SECONDS * 1000 - (Date.now() % (STEP_SECONDS * 1000)) + 1_000;
        await page.waitForTimeout(wait);
        await enterCode(page, email);
    }

    await expect(page).toHaveURL(/\/dashboard/);
}

import { defineConfig, devices } from '@playwright/test';

// P3-7: real browser E2E for the admin app (forms, media picker, workflow —
// things Pest's HTTP test client exercises at the request level but can't
// click through). Logs in once as a seeded editor (d.sattorov@khf.tj) and
// reuses that session. Everyone who may publish signs in with a 2FA code:
// the stand seeds each demo account a TOTP secret from DEMO_TWO_FACTOR_SECRET
// and tests/e2e/fixtures/login.ts derives the same code
// (CMS_E2E_TWO_FACTOR_SECRET, «khf-e2e-stand» by default).
//
// Specs create and change data: run them only against a stand with its own
// database, never the working one — a lerd worktree with `lerd db:isolate`
// (DEPLOYMENT.md, «E2E-стенд»).

// Стенд можно не поднимать самим: если CMS уже работает (например, в
// контейнере lerd), путь к ней передаётся через CMS_E2E_BASE_URL. Иначе
// `artisan serve` пытается занять порт, который уже проброшен контейнером,
// и прогон падает не по вине тестов.
const baseURL = process.env.CMS_E2E_BASE_URL ?? 'http://127.0.0.1:8848';
const startsOwnServer = !process.env.CMS_E2E_BASE_URL;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
        // Локальный стенд ходит по сертификату mkcert, которого нет в
        // хранилище Playwright.
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts/,
        },
        {
            name: 'chromium',
            testIgnore: /auth\.setup\.ts/,
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/e2e/.auth/editor.json',
            },
        },
    ],
    webServer: startsOwnServer
        ? {
              command: 'php artisan serve --port=8848',
              url: baseURL,
              reuseExistingServer: !process.env.CI,
              timeout: 30_000,
          }
        : undefined,
});

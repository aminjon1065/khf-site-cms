import { defineConfig, devices } from '@playwright/test';

// P3-7: real browser E2E for the admin app (forms, media picker, workflow —
// things Pest's HTTP test client exercises at the request level but can't
// click through). Logs in once as a seeded, 2FA-exempt editor account
// (UserSeeder: d.sattorov@khf.tj has `twoFactor => false`) and reuses that
// session — every 2FA-required role (admin/chief_editor/approver/...)
// is out of scope here until a TOTP-code-generation strategy exists for
// tests; see PROGRESS.md and the spawned follow-up task for what that
// still leaves uncovered (menu management, approve/publish).

// Стенд можно не поднимать самим: если CMS уже работает (например, в
// контейнере lerd по https://khf-site-cms.test), путь к ней передаётся через
// CMS_E2E_BASE_URL. Иначе `artisan serve` пытается занять порт, который уже
// проброшен контейнером, и прогон падает не по вине тестов.
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

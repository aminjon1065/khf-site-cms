import { defineConfig, devices } from '@playwright/test';

// P3-7: real browser E2E for the admin app (forms, media picker, workflow —
// things Pest's HTTP test client exercises at the request level but can't
// click through). Logs in once as a seeded, 2FA-exempt editor account
// (UserSeeder: d.sattorov@khf.tj has `twoFactor => false`) and reuses that
// session — every 2FA-required role (admin/chief_editor/approver/...)
// is out of scope here until a TOTP-code-generation strategy exists for
// tests; see PROGRESS.md and the spawned follow-up task for what that
// still leaves uncovered (menu management, approve/publish).

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8848',
        trace: 'on-first-retry',
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
    webServer: {
        command: 'php artisan serve --port=8848',
        url: 'http://127.0.0.1:8848',
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});

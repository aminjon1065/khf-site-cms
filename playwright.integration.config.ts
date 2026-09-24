import { defineConfig, devices } from '@playwright/test';

// E-1: the CMS and the public site together. An editor publishes, edits and
// unpublishes a news item in the CMS; the site — built and started for real
// against this CMS — must follow through the revalidation webhook within
// seconds, not the 60-second ISR timer.
//
// Needs, all running: this CMS with its own database (CMS_E2E_BASE_URL), the
// site built against it (SITE_E2E_BASE_URL), and the CMS webhook
// (FRONTEND_REVALIDATION_URL) pointed at that site. CI sets it all up in the
// `integration` job of .github/workflows/tests.yml.
const baseURL = process.env.CMS_E2E_BASE_URL ?? 'http://127.0.0.1:8848';

export default defineConfig({
    testDir: './tests/integration',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    timeout: 180_000,
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        trace: 'retain-on-failure',
        // A local stand serves the CMS with an mkcert certificate.
        ignoreHTTPSErrors: true,
    },
});

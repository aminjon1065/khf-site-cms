import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const DIRNAME = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(DIRNAME, '..', '..');

// P3-7: real browser coverage for a form + the media (cover) upload, using
// the seeded 2FA-exempt editor (see auth.setup.ts). Deliberately narrow —
// see PROGRESS.md for what P3-7 still doesn't cover (menu management,
// approve/publish, the "pick from library" half of the media picker) and
// why.

const DRAFT_TITLE = 'E2E-тест: черновик из формы (P3-7)';

test.afterEach(() => {
    // Cleanup goes through artisan, not the UI: `editor` (this suite's only
    // 2FA-exempt role, see playwright.config.ts) has no news.delete permission
    // — by design, not an oversight (PermissionMatrix grants delete only to
    // 2FA-required roles) — so there is no in-permission, 2FA-free way to
    // delete this draft through the app itself. Matched on the title so a
    // failed run's leftovers get swept up by the next run too, not just the
    // row this run created.
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\News::where('title->ru', 'like', '%${DRAFT_TITLE}%')->get()->each->forceDelete();`,
        ],
        { cwd: PROJECT_ROOT },
    );
});

test('editor creates a news draft with a cover image via the form, then deletes it', async ({
    page,
}) => {
    await page.goto('/news/create');

    // The title field's <label> isn't id-associated (a pre-existing gap, not
    // introduced here) and its own accessible name collides with the rich-text
    // toolbar's "Заголовок 2/3/4" heading buttons under getByLabel's substring
    // match — placeholder is the reliable selector here.
    await page
        .getByPlaceholder('Например: КЧС провёл учения…')
        .fill(DRAFT_TITLE);
    await page
        .locator('input[type="file"]')
        .setInputFiles(path.join(DIRNAME, 'fixtures', 'cover.png'));

    // The cover preview <img> only appears once the file is picked up client-side.
    await expect(page.locator("img[alt='']").first()).toBeVisible();

    await page.getByRole('button', { name: 'Сохранить черновик' }).click();

    await expect(page).toHaveURL(/\/news$/);
    await expect(
        page.getByText(DRAFT_TITLE, { exact: true }).first(),
    ).toBeVisible();
});

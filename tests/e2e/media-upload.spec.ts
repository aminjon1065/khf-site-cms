import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Медиатека принимает сразу несколько файлов, как WordPress: подходящие
// загружаются по одному, неподходящий отклоняется с причиной и не мешает
// остальным. Фото без описания видны в фильтре «Фото без описания».

const DIRNAME = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(DIRNAME, '..', '..');
const PHOTO = readFileSync(path.join(DIRNAME, 'fixtures', 'cover.png'));

function removeTestFiles(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `Spatie\\MediaLibrary\\MediaCollections\\Models\\Media::where('file_name', 'like', 'e2e-upload-%')->get()->each(fn ($media) => $media->model?->forceDelete());`,
        ],
        { cwd: PROJECT_ROOT },
    );
}

test.beforeEach(removeTestFiles);
test.afterEach(removeTestFiles);

test('several files upload at once and a wrong one is refused with the reason', async ({
    page,
}) => {
    await page.goto('/media');

    await page.locator('input[type="file"]').setInputFiles([
        { name: 'e2e-upload-1.png', mimeType: 'image/png', buffer: PHOTO },
        { name: 'e2e-upload-2.png', mimeType: 'image/png', buffer: PHOTO },
        {
            name: 'e2e-upload-setup.exe',
            mimeType: 'application/x-msdownload',
            buffer: Buffer.from('MZ'),
        },
    ]);

    await expect(page.getByText(/^Загружено: 2\./)).toBeVisible();
    await expect(page.getByRole('alert')).toContainText('Не загружено: 1');
    await expect(page.getByRole('alert')).toContainText(
        '«e2e-upload-setup.exe»: подходят файлы',
    );

    await page
        .getByRole('combobox', { name: 'Фильтр файлов по типу' })
        .selectOption({ label: 'Фото без описания' });
    await expect(page.getByText('e2e-upload-1.png').first()).toBeVisible();
    await expect(page.getByText('e2e-upload-2.png').first()).toBeVisible();
});

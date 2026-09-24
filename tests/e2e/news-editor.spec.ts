import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const LIBRARY_PHOTO = 'E2E-фото медиатеки';

function tinker(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
    });
}

function removeLibraryPhoto(): void {
    tinker(
        `App\\Models\\MediaAsset::withTrashed()->where('title', '${LIBRARY_PHOTO}')->get()->each->forceDelete();`,
    );
}

/**
 * A photo of its own in the media library: a fresh installation (and CI)
 * has none, and the scenario must not depend on what somebody uploaded.
 */
function addLibraryPhoto(): void {
    tinker(`
        $asset = App\\Models\\MediaAsset::create(['title' => '${LIBRARY_PHOTO}', 'alt' => 'Спасатели на учениях']);
        $asset->addMedia(base_path('tests/e2e/fixtures/cover.png'))->preservingOriginal()->toMediaCollection('asset');
    `);
}

test('news editor opens a link dialog, counts words and enters focus mode', async ({
    page,
}) => {
    await page.goto('/news/create');

    const editor = page.locator('.re-content');
    await expect(editor).toBeVisible();

    await editor.click();
    await page.keyboard.type('КЧС провёл учения в Хатлонской области.');
    // Счётчик под редактором («6 слов»), а не строка чек-листа «Текст: 6 слов…».
    await expect(page.getByText(/^\d+ слов(о|а)?$/)).toBeVisible();
    await expect(page.getByText(/^\d+ знаков?$/)).toBeVisible();

    await page.getByRole('button', { name: 'Ссылка' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Ссылка' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Адрес ссылки').fill('https://khf.tj/alerts');
    await dialog.getByRole('button', { name: 'Вставить' }).click();
    await expect(dialog).toBeHidden();
    await expect(
        page.locator('.re-content a[href="https://khf.tj/alerts"]'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'На весь экран' }).click();
    await expect(page.locator('.re-shell.is-focus')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.re-shell.is-focus')).toHaveCount(0);

    await page.getByRole('button', { name: 'Исходный HTML' }).click();
    await expect(page.getByLabel('Исходный HTML')).toBeVisible();
    await expect(page.getByLabel('Исходный HTML')).toHaveValue(/учения/);
});

test('news editor lets you click a photo and edit it in place', async ({
    page,
}) => {
    await page.goto('/news/create');

    await expect(page.locator('.re-content')).toBeVisible();
    await page.getByRole('button', { name: 'Исходный HTML' }).click();
    await page
        .getByLabel('Исходный HTML')
        .fill(
            '<p>До фото</p><figure class="re-figure size-large align-center"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="Учения"></figure><p>После фото</p>',
        );
    await page.getByRole('button', { name: 'Визуальный режим' }).click();

    const photo = page.locator('.re-figure-frame img');
    await expect(photo).toBeVisible();
    await photo.click();

    await expect(
        page.getByRole('button', { name: 'Кадрировать и повернуть' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Заменить изображение' }),
    ).toBeVisible();

    await page.getByLabel('Подпись к изображению').fill('Спасатели на учениях');
    await page.getByRole('button', { name: 'Маленький' }).click();
    await expect(page.locator('.re-image-view.size-small')).toBeVisible();

    await page.getByRole('button', { name: 'Кадрировать и повернуть' }).click();
    await expect(
        page.getByRole('dialog', { name: 'Редактор изображения' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Отмена' }).click();

    await page.getByRole('button', { name: 'Удалить изображение' }).click();
    await expect(photo).toHaveCount(0);
});

test.describe('with a photo in the library', () => {
    test.beforeEach(() => {
        removeLibraryPhoto();
        addLibraryPhoto();
    });
    test.afterEach(removeLibraryPhoto);

    test('news editor inserts a library photo, a youtube video and can delete a table', async ({
        page,
    }) => {
        await page.goto('/news/create');
        await expect(page.locator('.re-content')).toBeVisible();

        await page
            .getByRole('button', { name: 'Изображение из медиатеки' })
            .click();
        const media = page.getByRole('dialog', { name: 'Медиатека' });
        await expect(media).toBeVisible();
        await media.locator('.media-tile-main').first().click();
        await expect(media).toBeHidden();
        await expect(
            page.locator('.re-image-view, .re-content img').first(),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Видео с YouTube' }).click();
        const video = page.getByRole('dialog', { name: 'Видео YouTube' });
        await expect(video).toBeVisible();
        await video
            .getByLabel('Ссылка на видео')
            .fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        await video
            .getByRole('button', { name: 'Вставить', exact: true })
            .click();
        await expect(video).toBeHidden();
        // Именно iframe: обёртка [data-youtube-video] и вложенный в неё iframe
        // подходят под прежний селектор оба, и проверка падала на неоднозначности.
        // Видимый iframe — более сильное утверждение: обёртка есть и без него.
        await expect(page.locator('.re-content iframe')).toBeVisible();

        await page.getByRole('button', { name: 'Вставить таблицу' }).click();
        await expect(page.locator('.re-content table')).toBeVisible();
        await expect(
            page.getByRole('toolbar', { name: 'Таблица' }),
        ).toBeVisible();

        const rowsBefore = await page.locator('.re-content table tr').count();
        await page.getByRole('button', { name: 'Строка снизу' }).click();
        await expect(page.locator('.re-content table tr')).toHaveCount(
            rowsBefore + 1,
        );

        const cell = page.locator('.re-content th, .re-content td').first();
        await cell.click();
        await page.getByRole('button', { name: 'Заливка ячейки' }).click();
        await page.getByRole('button', { name: 'Синий КЧС' }).click();
        await expect(cell).toHaveCSS('background-color', 'rgb(215, 226, 234)');

        const edge = await cell.boundingBox();

        if (edge) {
            await page.mouse.move(edge.x + edge.width - 2, edge.y + 8);
            await expect(
                page.locator('.column-resize-handle').first(),
            ).toBeAttached();
        }

        await page.getByRole('button', { name: 'Удалить таблицу' }).click();
        await expect(page.locator('.re-content table')).toHaveCount(0);
        await expect(
            page.getByRole('toolbar', { name: 'Таблица' }),
        ).toHaveCount(0);
    });
});

import { expect, test } from '@playwright/test';

test('news editor opens a link dialog, counts words and enters focus mode', async ({
    page,
}) => {
    await page.goto('/news/create');

    const editor = page.locator('.re-content');
    await expect(editor).toBeVisible();

    await editor.click();
    await page.keyboard.type('КЧС провёл учения в Хатлонской области.');
    await expect(page.getByText(/слов/)).toBeVisible();
    await expect(page.getByText(/знаков/)).toBeVisible();

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

test('news editor inserts a library photo, a youtube video and can delete a table', async ({
    page,
}) => {
    await page.goto('/news/create');
    await expect(page.locator('.re-content')).toBeVisible();

    await page
        .getByRole('button', { name: 'Изображение из медиатеки' })
        .click();
    const media = page.getByRole('dialog', { name: 'Медиабиблиотека' });
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
    await video.getByRole('button', { name: 'Вставить', exact: true }).click();
    await expect(video).toBeHidden();
    await expect(
        page.locator('[data-youtube-video], .re-content iframe'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Вставить таблицу' }).click();
    await expect(page.locator('.re-content table')).toBeVisible();
    await expect(page.getByRole('toolbar', { name: 'Таблица' })).toBeVisible();

    const rowsBefore = await page.locator('.re-content table tr').count();
    await page.getByRole('button', { name: 'Строка снизу' }).click();
    await expect(page.locator('.re-content table tr')).toHaveCount(
        rowsBefore + 1,
    );

    const cell = page.locator('.re-content th, .re-content td').first();
    await cell.click();
    await page.getByRole('button', { name: 'Заливка ячейки' }).click();
    await page.getByRole('button', { name: 'Синий КЧС' }).click();
    await expect(cell).toHaveCSS(
        'background-color',
        'rgb(215, 226, 234)',
    );

    const edge = await cell.boundingBox();
    if (edge) {
        await page.mouse.move(edge.x + edge.width - 2, edge.y + 8);
        await expect(page.locator('.column-resize-handle').first()).toBeAttached();
    }

    await page.getByRole('button', { name: 'Удалить таблицу' }).click();
    await expect(page.locator('.re-content table')).toHaveCount(0);
    await expect(page.getByRole('toolbar', { name: 'Таблица' })).toHaveCount(0);
});

import { expect, test } from '@playwright/test';

// Новость может существовать на одном языке. Предпросмотр открывался на
// русской вкладке и сообщал «показана русская fallback-версия», хотя русской
// версии не было, — а публичный сайт язык вообще не подменяет: без заголовка
// и текста на языке материала на этой версии сайта просто нет.
const TITLE = 'Машқҳои КҲФ дар вилояти Хатлон';

test('preview of a Tajik-only news opens in Tajik and states it is absent in Russian', async ({
    page,
}) => {
    await page.goto('/news/create');

    await page.getByRole('tab', { name: /^ТҶ/ }).click();
    await page.getByRole('textbox', { name: 'Заголовок новости' }).fill(TITLE);

    const dialog = page.getByRole('dialog', {
        name: 'Предпросмотр публикации',
    });
    const openPreview = () =>
        page.getByRole('button', { name: 'Предпросмотр', exact: true }).click();

    // Заголовок без текста на сайт не попадает — предпросмотр так и говорит.
    await openPreview();
    await expect(dialog.getByRole('status')).toHaveText(
        'Для ТҶ нет текста — на таджикской версии сайта материал не появится.',
    );
    await expect(dialog.getByRole('heading', { name: TITLE })).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();

    await page.locator('.re-content').click();
    await page.keyboard.type('Машқҳо дар вилояти Хатлон гузаронида шуданд.');
    await openPreview();

    await expect(dialog.getByRole('heading', { name: TITLE })).toBeVisible();
    await expect(dialog).not.toContainText('fallback');

    await dialog.getByRole('button', { name: 'РУ', exact: true }).click();

    await expect(dialog.getByRole('status')).toHaveText(
        'Для РУ нет заголовка — на русской версии сайта материал не появится.',
    );
    await expect(dialog.getByRole('heading', { name: TITLE })).toHaveCount(0);
});

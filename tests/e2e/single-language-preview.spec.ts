import { expect, test } from '@playwright/test';

// Новость может существовать на одном языке. Предпросмотр открывался на
// русской вкладке и сообщал «показана русская fallback-версия», хотя русской
// версии не было, — а публичный сайт язык вообще не подменяет: без заголовка
// на языке материала на этой версии сайта просто нет.
const TITLE = 'Машқҳои КҲФ дар вилояти Хатлон';

test('preview of a Tajik-only news opens in Tajik and states it is absent in Russian', async ({
    page,
}) => {
    await page.goto('/news/create');

    await page.getByRole('tab', { name: /^ТҶ/ }).click();
    await page
        .getByRole('textbox', { name: 'Заголовок*', exact: true })
        .fill(TITLE);
    await page
        .getByRole('button', { name: 'Предпросмотр', exact: true })
        .click();

    const dialog = page.getByRole('dialog', {
        name: 'Предпросмотр публикации',
    });

    await expect(dialog.getByRole('heading', { name: TITLE })).toBeVisible();
    await expect(dialog).not.toContainText('fallback');

    await dialog.getByRole('button', { name: 'RU', exact: true }).click();

    await expect(dialog.getByRole('status')).toHaveText(
        'Для RU нет заголовка — на русской версии сайта материал не появится.',
    );
    await expect(dialog.getByRole('heading', { name: TITLE })).toHaveCount(0);
});

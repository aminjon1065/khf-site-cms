import { test, expect } from '@playwright/test';

// Выпадающее меню строки списка позиционируется замером: React рисует его,
// `useLayoutEffect` считает координаты по триггеру и показывает.
//
// Раньше в этом эффекте стоял `setReady(false)` на закрытии — вызов setState
// прямо в эффекте, то есть лишний цикл рендера на каждое закрытие ради
// состояния, которого всё равно не видно (меню размонтируется вместе с
// `open`). Сброс убран; проверяем, что от этого не пострадало главное: меню
// встаёт у своего триггера и при повторном открытии тоже, а не остаётся в
// левом верхнем углу со старыми координатами.

test('row menu is positioned next to its trigger on every open', async ({
    page,
}) => {
    await page.goto('/news');

    const triggers = page.getByRole('button', { name: 'Действия' });
    await expect(triggers.first()).toBeVisible();

    const menu = page.locator('.ui-menu');

    for (const index of [0, 1, 0]) {
        const trigger = triggers.nth(index);
        const triggerBox = await trigger.boundingBox();

        await trigger.click();
        await expect(menu).toBeVisible();

        const menuBox = await menu.boundingBox();

        expect(menuBox, 'меню должно иметь размеры').not.toBeNull();
        expect(triggerBox, 'триггер должен иметь размеры').not.toBeNull();

        // Не в левом верхнем углу: там оно оказывалось бы со стартовыми
        // координатами {0,0}, то есть если бы замер не отработал.
        expect(menuBox!.x).toBeGreaterThan(0);
        expect(menuBox!.y).toBeGreaterThan(0);

        // И рядом со своим триггером, а не у соседней строки. Допуск — высота
        // меню плюс отступ: строки таблицы идут примерно через 50 px, поэтому
        // широкий допуск пропустил бы меню, открытое у чужой строки, и тест
        // ничего бы не проверял.
        expect(
            Math.abs(menuBox!.y - triggerBox!.y),
            'меню открывается у своей строки, а не у соседней',
        ).toBeLessThan(menuBox!.height + 24);

        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
    }
});

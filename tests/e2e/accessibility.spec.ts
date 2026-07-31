import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const CONTROL_ROUTES = [
    '/dashboard',
    '/news',
    '/media',
    '/news/create',
    '/editorial/trash',
    '/editorial/translations',
] as const;

async function expectNoBlockingViolations(page: Page) {
    const results = await new AxeBuilder({ page })
        .withTags(WCAG_TAGS)
        .analyze();
    const blockingViolations = results.violations.filter(
        (violation) =>
            violation.impact === 'serious' || violation.impact === 'critical',
    );
    const summary = blockingViolations.map(
        (violation) =>
            `${violation.id}: ${violation.help} (${violation.nodes.length} nodes; ${violation.nodes
                .slice(0, 3)
                .map((node) => node.target.join(' '))
                .join(', ')})`,
    );

    expect(summary, summary.join('\n')).toEqual([]);
}

for (const route of CONTROL_ROUTES) {
    test(`${route} has no serious or critical WCAG violations`, async ({
        page,
    }) => {
        await page.goto(route);
        await expectNoBlockingViolations(page);
    });
}

test('dialogs trap keyboard focus and return it to their trigger', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const notifications = page.getByRole('button', {
        name: 'Уведомления',
    });
    await notifications.focus();
    await page.keyboard.press('Enter');

    const drawer = page.getByRole('dialog', { name: 'Уведомления' });
    const closeDrawer = drawer.getByRole('button', { name: 'Закрыть' });

    await expect(drawer).toBeVisible();
    await expect(closeDrawer).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(closeDrawer).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(closeDrawer).toBeFocused();

    const dialogResults = await new AxeBuilder({ page })
        .include('.ui-drawer')
        .withTags(WCAG_TAGS)
        .analyze();
    const dialogSummary = dialogResults.violations.map(
        (violation) =>
            `${violation.id}: ${violation.help} (${violation.nodes.length} nodes)`,
    );

    expect(dialogSummary, dialogSummary.join('\n')).toEqual([]);

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(notifications).toBeFocused();

    const search = page.getByRole('button', { name: 'Поиск по CMS' });
    await search.focus();
    await page.keyboard.press('Enter');

    const palette = page.getByRole('dialog', {
        name: 'Командная палитра',
    });
    await expect(palette).toBeVisible();
    await expect(
        palette.getByRole('combobox', { name: 'Командная палитра' }),
    ).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(palette).toBeHidden();
    await expect(search).toBeFocused();

    const focusStyle = await search.evaluate((element) => {
        const style = getComputedStyle(element);

        return {
            outlineStyle: style.outlineStyle,
            outlineWidth: style.outlineWidth,
        };
    });

    expect(focusStyle.outlineStyle).not.toBe('none');
    expect(Number.parseFloat(focusStyle.outlineWidth)).toBeGreaterThanOrEqual(
        2,
    );
});

test('editorial form reflows and exposes non-drag and live-region alternatives', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/news/create');

    await expect(
        page.getByRole('heading', { name: 'Новая новость' }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Загрузить' })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Из медиатеки' }),
    ).toBeVisible();
    await expect(page.locator('[aria-live="polite"]')).toContainText(
        'Автосохранение включено',
    );

    const viewport = await page.locator('html').evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));

    expect(viewport.scrollWidth).toBe(viewport.clientWidth);
});

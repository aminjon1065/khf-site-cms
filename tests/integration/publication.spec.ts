import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import { logIn } from '../e2e/fixtures/login';

// A news item goes on the public site, changes there and comes off it — the
// way an editor does it, through the CMS, signed in with a 2FA code. The site
// caches its pages (ISR, 60 s); every change below must show within
// WEBHOOK_WINDOW, which only the revalidation webhook can make happen. So a
// broken webhook contract, secret or tag fails here, not in production.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const SITE = (process.env.SITE_E2E_BASE_URL ?? 'http://127.0.0.1:3000').replace(
    /\/+$/,
    '',
);
const PREFIX = 'E2E-интеграция';
const STAMP = Date.now().toString(36);
const TITLE = `${PREFIX} ${STAMP}: учения спасателей`;
const EDITED = `${PREFIX} ${STAMP}: учения завершены`;

/** Well inside the site's 60-second ISR period. */
const WEBHOOK_WINDOW = 20_000;

function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
        encoding: 'utf8',
    });
}

function removeTestNews(): void {
    tinker(
        `App\\Models\\News::withTrashed()->where('title->ru', 'like', '${PREFIX}%')->get()->each->forceDelete();`,
    );
}

/** `{id}:{slug}` of the news item with this Russian title. */
function newsItem(title: string): { id: number; slug: string } {
    const output = tinker(
        `$news = App\\Models\\News::where('title->ru', '${title}')->firstOrFail(); echo 'ITEM='.$news->id.':'.$news->slug;`,
    );
    const match = output.match(/ITEM=(\d+):(\S+)/);

    if (!match) {
        throw new Error(`No news item «${title}»: ${output}`);
    }

    return { id: Number(match[1]), slug: match[2] };
}

async function site(
    request: APIRequestContext,
    pathname: string,
): Promise<{ status: number; html: string }> {
    const response = await request.get(`${SITE}${pathname}`, {
        failOnStatusCode: false,
    });

    return { status: response.status(), html: await response.text() };
}

/** The <url> entry of sitemap.xml for this address, or null. */
function sitemapEntry(xml: string, url: string): string | null {
    return (
        xml
            .split('<url>')
            .find((entry) => entry.includes(`<loc>${url}</loc>`)) ?? null
    );
}

async function eventually(
    check: () => Promise<boolean>,
    message: string,
): Promise<void> {
    await expect
        .poll(check, {
            message,
            timeout: WEBHOOK_WINDOW,
            intervals: [500, 1000, 1000, 2000],
        })
        .toBe(true);
}

test.beforeAll(removeTestNews);
test.afterAll(removeTestNews);

test('a news item reaches the site, changes and leaves it through the webhook', async ({
    page,
    request,
}) => {
    // An editor publishes news directly (no approval) and signs in with a code.
    await logIn(page, 'd.sattorov@khf.tj');

    await page.goto('/news/create');
    await page.getByRole('textbox', { name: 'Заголовок новости' }).fill(TITLE);
    await page
        .getByRole('textbox', { name: 'Краткое описание (лид)' })
        .fill('Спасатели отработали эвакуацию из зоны селя.');
    await page.locator('.re-content').click();
    await page.keyboard.type('Учения прошли в Хатлонской области.');

    // The site has the news list cached before the publication: from here on
    // only the webhook can make it show the new item in time.
    const before = await site(request, '/ru/news');
    expect(before.status).toBe(200);
    expect(before.html).not.toContain(TITLE);

    await page
        .getByRole('button', { name: 'Опубликовать', exact: true })
        .first()
        .click();
    await expect(page).toHaveURL(/\/news$/);

    const { id, slug } = newsItem(TITLE);
    const detail = `/ru/news/${slug}`;

    await eventually(
        async () => (await site(request, '/ru/news')).html.includes(TITLE),
        'the site lists the published item',
    );
    await eventually(async () => {
        const item = await site(request, detail);

        return item.status === 200 && item.html.includes(TITLE);
    }, 'the item has its page on the site');
    await eventually(async () => {
        const entry = sitemapEntry(
            (await site(request, '/sitemap.xml')).html,
            `${SITE}${detail}`,
        );

        return entry !== null && /<lastmod>[^<]+<\/lastmod>/.test(entry);
    }, 'sitemap.xml lists the item with its date');

    // An edit of the live item: the page was just rendered and cached.
    await page.goto(`/news/${id}/edit`);
    await page.getByRole('textbox', { name: 'Заголовок новости' }).fill(EDITED);
    await page
        .getByRole('button', { name: 'Обновить', exact: true })
        .first()
        .click();
    await expect(page).toHaveURL(/\/news$/);
    await expect(
        page.getByText('Изменения сохранены — они уже на сайте.').first(),
    ).toBeVisible();

    await eventually(async () => {
        const item = await site(request, detail);

        return item.status === 200 && item.html.includes(EDITED);
    }, 'the item page shows the edit');

    // Off the site: the page goes, and so does the entry in the list and map.
    await page.goto(`/news/${id}/edit`);
    await page.getByRole('button', { name: 'Другие действия' }).first().click();
    await page.getByRole('menuitem', { name: /Снять с публикации/ }).click();
    const dialog = page.getByRole('dialog', { name: 'Снять с публикации?' });
    await dialog
        .getByLabel('Комментарий (обязательно)')
        .fill('Интеграционная проверка');
    await dialog.getByRole('button', { name: 'Снять с публикации' }).click();
    await expect(dialog).toBeHidden();

    await eventually(
        async () => (await site(request, detail)).status === 404,
        'the item page is gone',
    );
    await eventually(
        async () => !(await site(request, '/ru/news')).html.includes(EDITED),
        'the site no longer lists the item',
    );
    await eventually(
        async () =>
            sitemapEntry(
                (await site(request, '/sitemap.xml')).html,
                `${SITE}${detail}`,
            ) === null,
        'sitemap.xml no longer lists the item',
    );
});

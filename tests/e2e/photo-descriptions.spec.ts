import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Фото в тексте без описания незрячий читатель не «увидит». Раньше вместо
// описания сохранялось имя файла («IMG_2034.jpg»). Теперь редактор помечает
// такие фото «Нет описания», описание вводится прямо на фото, а декоративное
// фото отмечается флажком.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const TITLE = 'E2E-описания фото';

function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
        encoding: 'utf8',
    });
}

function removeTestNews(): void {
    tinker(
        `App\\Models\\News::withTrashed()->where('title->ru', 'like', '${TITLE}%')->get()->each->forceDelete();`,
    );
}

test.beforeEach(removeTestNews);
test.afterEach(removeTestNews);

test('an undescribed photo is flagged, described on the spot or marked decorative', async ({
    page,
}) => {
    const output = tinker(`
        $news = App\\Models\\News::create([
            'title' => ['ru' => '${TITLE}'],
            'body' => ['ru' => '<figure class="re-figure"><img src="/storage/e2e/IMG_2034.jpg" alt="IMG_2034.jpg"></figure><p>Текст</p><figure class="re-figure"><img src="/storage/e2e/line.png" alt=""></figure>'],
            'status' => 'draft',
        ]);
        echo 'ID='.$news->id;
    `);
    const id = Number(output.match(/ID=(\d+)/)?.[1]);

    await page.goto(`/news/${id}/edit`);

    const flags = page.getByRole('button', { name: 'Нет описания' });
    await expect(flags).toHaveCount(2);

    // The file name is not a description: describe the first photo.
    await flags.first().click();
    await page.keyboard.type('Спасатели на учениях в Хатлоне');
    await expect(flags).toHaveCount(1);

    // The second one is decorative.
    await flags.first().click();
    await page
        .getByRole('checkbox', {
            name: 'Декоративное фото — описание не нужно',
        })
        .check();
    await expect(flags).toHaveCount(0);

    // Ctrl+S right from the photo's field: the save must go through.
    const saving = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname === `/news/${id}`,
    );
    await page.keyboard.press('Control+s');
    await saving;
    await expect(page).toHaveURL(new RegExp(`/news/${id}/edit$`));

    const saved = tinker(
        `echo App\\Models\\News::find(${id})->getTranslation('body', 'ru');`,
    );
    expect(saved).toContain('alt="Спасатели на учениях в Хатлоне"');
    expect(saved).toContain('data-decorative="true"');
    expect(saved).not.toContain('alt="IMG_2034.jpg"');

    await page.reload();
    await expect(
        page.getByRole('button', { name: 'Нет описания' }),
    ).toHaveCount(0);
});

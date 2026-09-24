import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Форма документа в редакторе, как у новости: название и файлы по языкам на
// холсте, реквизиты в панели справа. Ctrl+S сохраняет черновик и оставляет в
// редакторе: название, реквизиты и файл на месте.

const DIRNAME = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(DIRNAME, '..', '..');
const TITLE = 'E2E-документ: приказ о готовности';

function removeTestDocuments(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\Document::withTrashed()->where('name->ru', 'like', 'E2E-документ%')->get()->each->forceDelete();`,
        ],
        { cwd: PROJECT_ROOT },
    );
}

test.beforeEach(removeTestDocuments);
test.afterEach(removeTestDocuments);

test('editor adds a document with requisites and a Russian file, then saves with Ctrl+S', async ({
    page,
}) => {
    await page.goto('/documents/create');

    await page.getByRole('textbox', { name: 'Название документа' }).fill(TITLE);

    const panel = page.getByRole('complementary', {
        name: 'Настройки документа',
    });
    await panel
        .getByRole('combobox', { name: 'Тип документа' })
        .selectOption({ label: 'Приказ' });
    await panel.getByRole('textbox', { name: 'Номер' }).fill('№ 12');
    await panel.getByLabel('Дата документа').fill('2026-09-01');

    await page
        .locator('#document-file-ru')
        .setInputFiles(path.join(DIRNAME, 'fixtures', 'document.pdf'));
    await expect(page.getByText('загрузится при сохранении')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Заменить файл: Русский (РУ)' }),
    ).toBeVisible();

    await page.keyboard.press('Control+s');
    await expect(page).toHaveURL(/\/documents\/\d+\/edit$/);

    await page.reload();
    await expect(
        page.getByRole('textbox', { name: 'Название документа' }),
    ).toHaveValue(TITLE);
    await expect(panel.getByRole('textbox', { name: 'Номер' })).toHaveValue(
        '№ 12',
    );
    await expect(
        page.getByRole('link', { name: 'document.pdf' }),
    ).toBeVisible();
    // The other languages have no file yet.
    await expect(
        page.getByRole('button', { name: 'Выбрать файл: Таджикский (ТҶ)' }),
    ).toBeVisible();
});

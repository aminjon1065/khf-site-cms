import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// Правка опубликованного материала без права публикации уходит на
// согласование, а фото и файлы в ней сервер не принимает. Редактор поэтому не
// предлагает их менять: показывает сохранённое и объясняет, кто это делает.
// Вошедший редактор публикует новости, но не документы и не инструкции.

const PROJECT_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const NOTE =
    'Фото и файлы опубликованного материала меняет сотрудник с правом публикации.';

function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: PROJECT_ROOT,
        encoding: 'utf8',
    });
}

function removeTestMaterials(): void {
    tinker(`
        App\\Models\\Document::withTrashed()->where('name->ru', 'like', 'E2E-замок%')->get()->each->forceDelete();
        App\\Models\\Instruction::withTrashed()->where('name->ru', 'like', 'E2E-замок%')->get()->each->forceDelete();
    `);
}

function createdId(output: string): number {
    const match = output.match(/ID=(\d+)/);

    if (!match) {
        throw new Error(`tinker did not report an id: ${output}`);
    }

    return Number(match[1]);
}

test.beforeEach(removeTestMaterials);
test.afterEach(removeTestMaterials);

test('a live document shows its files without offering to change them', async ({
    page,
}) => {
    const id = createdId(
        tinker(`
            $document = App\\Models\\Document::create([
                'name' => ['ru' => 'E2E-замок: опубликованный приказ'],
                'doc_type' => 'order',
                'status' => 'published',
                'published_at' => now(),
            ]);
            echo 'ID='.$document->id;
        `),
    );

    await page.goto(`/documents/${id}/edit`);

    await expect(page.getByText(NOTE)).toBeVisible();
    await expect(
        page.getByRole('button', { name: /^(Выбрать|Заменить) файл/ }),
    ).toHaveCount(0);
    // The text of the material still goes to approval as usual.
    await expect(
        page.getByRole('button', {
            name: 'Отправить изменения на согласование',
        }),
    ).toBeVisible();
});

test('a live instruction shows its illustration and files without offering to change them', async ({
    page,
}) => {
    const id = createdId(
        tinker(`
            $instruction = App\\Models\\Instruction::create([
                'name' => ['ru' => 'E2E-замок: опубликованная инструкция'],
                'summary' => ['ru' => 'Кратко'],
                'hazard_type' => 'earthquake',
                'status' => 'published',
                'published_at' => now(),
            ]);
            echo 'ID='.$instruction->id;
        `),
    );

    await page.goto(`/instructions/${id}/edit`);
    await page.getByRole('tab', { name: 'Иллюстрация' }).click();

    await expect(page.getByText('Иллюстрации нет.')).toBeVisible();
    await expect(page.getByText(NOTE).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Загрузить' })).toHaveCount(
        0,
    );
    await expect(
        page.getByRole('button', { name: 'Добавить файлы' }),
    ).toHaveCount(0);
});

import assert from 'node:assert/strict';
import test from 'node:test';
import {
    acceptOf,
    limitHint,
    uploadProblem,
} from '../../resources/js/lib/uploads.ts';

// The limits the server shares (App\Support\UploadLimits::forClient()).
const image = {
    formats: ['jpg', 'jpeg', 'png', 'webp'],
    label: 'JPG, PNG или WebP',
    max_mb: 10,
};
const file = {
    formats: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
    label: 'PDF, DOC(X), XLS(X) или PPT(X)',
    max_mb: 20,
};

const picked = (name: string, megabytes: number) =>
    ({ name, size: megabytes * 1024 * 1024 }) as File;

test('a file input accepts the formats the server takes', () => {
    assert.equal(acceptOf(image), '.jpg,.jpeg,.png,.webp');
    assert.equal(
        acceptOf(image, file),
        '.jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx',
    );
    assert.equal(limitHint(image), 'JPG, PNG или WebP до 10 МБ');
});

test('a photo within the limits goes through, whatever the case of its extension', () => {
    assert.equal(
        uploadProblem(picked('IMG_2034.JPG', 6), image, 'image'),
        null,
    );
    assert.equal(uploadProblem(picked('схема.webp', 10), image, 'image'), null);
});

test('a photo in another format or too large is turned away with the reason', () => {
    assert.equal(
        uploadProblem(picked('IMG_2034.HEIC', 3), image, 'image'),
        '«IMG_2034.HEIC»: нужно изображение JPG, PNG или WebP.',
    );
    assert.equal(
        uploadProblem(picked('camera.jpg', 11), image, 'image'),
        '«camera.jpg» больше 10 МБ — уменьшите изображение.',
    );
});

test('documents are checked by the file limits', () => {
    assert.equal(uploadProblem(picked('отчёт.pptx', 19), file, 'file'), null);
    assert.equal(
        uploadProblem(picked('setup.exe', 1), file, 'file'),
        '«setup.exe»: подходят файлы PDF, DOC(X), XLS(X) или PPT(X).',
    );
    assert.equal(
        uploadProblem(picked('скан.pdf', 25), file, 'file'),
        '«скан.pdf» больше 20 МБ — сожмите файл или разделите его на части.',
    );
    assert.equal(
        uploadProblem(picked('README', 1), file, 'file'),
        '«README»: подходят файлы PDF, DOC(X), XLS(X) или PPT(X).',
    );
});

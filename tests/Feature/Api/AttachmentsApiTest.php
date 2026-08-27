<?php

use App\Models\Instruction;
use App\Models\News;
use Illuminate\Http\UploadedFile;

/**
 * Файл с настоящим содержимым: UploadedFile::fake()->create() отдаёт заявленный
 * размер, но пустой файл, а медиатека записывает фактический — размер в API
 * оказался бы «0 Б».
 */
function attachmentFile(string $name, int $kilobytes): string
{
    $path = sys_get_temp_dir().'/'.$name;
    file_put_contents($path, str_repeat('a', $kilobytes * 1024));

    return $path;
}

// Памятки и материалы в PDF. На странице инструкции и в статье под них уже
// была вёрстка, но данных не существовало: блок «Материалы» всегда получал
// пустой список.

it('returns attachments of a news item with type and human size', function () {
    $news = News::factory()->published()->create();
    $news->addMedia(attachmentFile('pamyatka.pdf', 1200))
        ->usingName('Памятка о поведении при землетрясении')
        ->toMediaCollection('attachments');

    $response = $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertOk();

    expect($response->json('data.attachments'))->toHaveCount(1)
        ->and($response->json('data.attachments.0.title'))
        ->toBe('Памятка о поведении при землетрясении')
        ->and($response->json('data.attachments.0.ext'))->toBe('PDF')
        // Строка идёт прямо в интерфейс рядом со ссылкой на файл.
        ->and($response->json('data.attachments.0.size'))->toBe('1,2 МБ');
});

it('formats the size for the requested locale', function () {
    // Название на обоих языках: без английского перевода публичный запрос
    // с ?locale=en вернул бы 404, а не пустую выдачу.
    $instruction = Instruction::factory()->published()->create([
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => 'Earthquake'],
    ]);
    $instruction->addMedia(attachmentFile('leaflet.pdf', 1200))
        ->usingName('Leaflet')
        ->toMediaCollection('attachments');

    $ru = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=ru")->assertOk();
    $en = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=en")->assertOk();

    expect($ru->json('data.attachments.0.size'))->toBe('1,2 МБ')
        ->and($en->json('data.attachments.0.size'))->toBe('1.2 MB');

    // Меньше мегабайта — килобайты, а не «0,8 МБ»: порог именно такой.
    $instruction->addMedia(attachmentFile('short.pdf', 300))
        ->usingName('Short')
        ->toMediaCollection('attachments');

    $again = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=ru")->assertOk();

    expect($again->json('data.attachments.1.size'))->toBe('300 КБ');
});

it('falls back to the file name when the editor left the name empty', function () {
    $instruction = Instruction::factory()->published()->create();
    $instruction->addMedia(UploadedFile::fake()->create('spravka.pdf', 10, 'application/pdf'))
        ->usingName('')
        ->toMediaCollection('attachments');

    $response = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=ru")->assertOk();

    expect($response->json('data.attachments.0.title'))->toBe('spravka.pdf');
});

it('returns an empty list when there are no attachments', function () {
    // Публичная часть в этом случае не выводит блок «Материалы».
    $news = News::factory()->published()->create();

    $response = $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertOk();

    expect($response->json('data.attachments'))->toBe([]);
});

it('omits attachments from list responses', function () {
    // В списках блок не выводится, поэтому и медиа тянуть незачем.
    $news = News::factory()->published()->create();
    $news->addMedia(UploadedFile::fake()->create('pamyatka.pdf', 10, 'application/pdf'))
        ->toMediaCollection('attachments');

    $response = $this->getJson('/api/v1/news?locale=ru')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('attachments');
});

it('keeps attachments of an unpublished item off the public disk', function () {
    // Диск тот же, что у обложки: у черновика вложения не должны быть
    // доступны по прямой ссылке до публикации.
    $news = News::factory()->create();
    $news->addMedia(UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf'))
        ->toMediaCollection('attachments');

    expect($news->getFirstMedia('attachments')->disk)
        ->toBe(config('media-library.private_disk_name'));
});

<?php

use App\Models\News;
use Illuminate\Http\UploadedFile;

// Подпись под фотографией принадлежит материалу. Публичная часть выводила
// вместо неё общую строку словаря («Фото: пресс-служба КЧС») — один и тот же
// текст стоял под любым снимком, независимо от того, что на нём изображено.

function newsWithCover(array $attributes = []): News
{
    $news = News::factory()->published()->create($attributes);
    $news->addMedia(UploadedFile::fake()->image('cover.jpg', 800, 450))
        ->toMediaCollection('cover');

    return $news;
}

it('returns the caption entered by the editor', function () {
    $news = newsWithCover([
        'cover_alt' => 'Спасатели на учениях',
        'cover_caption' => 'Учения в Согдийской области. Фото пресс-службы',
    ]);

    $response = $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertOk();

    expect($response->json('data.image_data.caption'))
        ->toBe('Учения в Согдийской области. Фото пресс-службы');
});

it('leaves the caption null when the editor did not enter one', function () {
    // Публичная часть в этом случае просто не рисует <figcaption>, а не
    // подставляет придуманный текст.
    $news = newsWithCover(['cover_alt' => 'Спасатели', 'cover_caption' => null]);

    $response = $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertOk();

    expect($response->json('data.image_data.caption'))->toBeNull();
});

it('treats a blank caption as absent', function () {
    $news = newsWithCover(['cover_alt' => 'Спасатели', 'cover_caption' => '   ']);

    $response = $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertOk();

    expect($response->json('data.image_data.caption'))->toBeNull();
});

it('keeps the caption separate from the alt text', function () {
    // Alt описывает снимок тем, кто его не видит; подпись читают все. Это
    // разные тексты, и подменять один другим нельзя.
    $news = newsWithCover([
        'cover_alt' => 'Вертолёт над горным склоном',
        'cover_caption' => 'Эвакуация альпинистов с пика Исмоили Сомони',
    ]);

    $response = $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertOk();

    expect($response->json('data.image_data.alt'))->toBe('Вертолёт над горным склоном')
        ->and($response->json('data.image_data.caption'))
        ->toBe('Эвакуация альпинистов с пика Исмоили Сомони');
});

<?php

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->fixturePath = storage_path('framework/testing/source-fixtures');
    config()->set('seeding.source.path', $this->fixturePath);

    if (is_dir($this->fixturePath)) {
        array_map(unlink(...), glob($this->fixturePath.'/*.json') ?: []);
    }
});

/**
 * The listing markup the command walks: Drupal renders each teaser as an
 * `<article id="node-N" class="node-article …">`.
 */
function listingHtml(int ...$nodeIds): string
{
    $articles = '';

    foreach ($nodeIds as $nodeId) {
        $articles .= <<<HTML
            <article id="node-{$nodeId}" class="node node-article node-teaser clearfix">
                <h2 class="title"><a href="/node/{$nodeId}">Заголовок {$nodeId}</a></h2>
            </article>
            HTML;
    }

    return "<html><body><div id=\"content\">{$articles}</div></body></html>";
}

/**
 * A full node page, trimmed to the parts the parser reads.
 */
function nodeHtml(): string
{
    return <<<'HTML'
        <html><body>
        <article id="node-4163" class="node node-article node-full clearfix">
          <span property="dc:title" content="КЧС Хатлон: Спасатели спасли трёх граждан"></span>
          <span class="submitted"><span property="dc:date dc:created" content="2026-06-14T08:51:58+05:00">Опубликована: 14/06/2026</span></span>
          <div class="content node-article">
            <div class="field field-name-field-image field-type-image field-label-hidden">
              <div class="field-items">
                <div class="field-item even" rel="og:image rdfs:seeAlso" resource="https://kchs.tj/sites/default/files/field/image/IMG_1138.jpg">
                  <img src="https://kchs.tj/sites/default/files/styles/232_155/public/field/image/IMG_1138.jpg?itok=x" width="232" height="155" alt="" />
                </div>
              </div>
            </div>
            <div class="field field-name-body field-type-text-with-summary field-label-hidden"><div class="field-items">
              <div class="field-item even" property="content:encoded">
                <p style="text-align:center" class="rtecenter"><strong>Трое граждан оказались отрезанными водой.</strong></p>
                <p>Подробности — в <a href="/sites/default/files/otchet.pdf">отчёте</a>.</p>
                <p>&nbsp;</p>
                <script>alert('x')</script>
                <img src="/inline.jpg" alt="" />
                <div class="uptolike-buttons">Поделиться</div>
              </div>
            </div></div>
            <div class="field field-name-field-gallery"><div class="field-items"><div class="field-item even">
              <div class="galleria-content clearfix" id="galleria-1">
                <a href="https://kchs.tj/styles/zoom/IMG_1135.jpg" rel="https://kchs.tj/sites/default/files/dop_photo/IMG_1135.jpg"><img src="https://kchs.tj/thumb.jpg" /></a>
              </div>
            </div></div></div>
            <div class="field field-name-field-tags field-type-taxonomy-term-reference field-label-above">
              <div class="field-label">Теги:&nbsp;</div>
              <div class="field-items">
                <div class="field-item even"><a href="/taxonomy/term/82">Новости</a></div>
                <div class="field-item odd"><a href="/taxonomy/term/90">УКЧС по Хатлону</a></div>
              </div>
            </div>
          </div>
          <footer><ul class="links inline"><li class="statistics_counter first last"><span>1779 просмотров</span></li></ul></footer>
        </article>
        </body></html>
        HTML;
}

it('writes a fixture with the fields the seeders read', function (): void {
    Http::fake([
        'kchs.tj/node?page=0' => Http::response(listingHtml(4163)),
        'kchs.tj/node/4163' => Http::response(nodeHtml()),
    ]);

    $this->artisan('khf:scrape-source', [
        '--news' => 1,
        '--announcements' => 0,
        '--locale' => ['ru'],
    ])->assertSuccessful();

    $payload = json_decode((string) file_get_contents($this->fixturePath.'/news-ru.json'), true);

    expect($payload['source'])->toBe('https://kchs.tj')
        ->and($payload['locale'])->toBe('ru')
        ->and($payload['items'])->toHaveCount(1);

    $item = $payload['items'][0];

    expect($item['source_id'])->toBe(4163)
        ->and($item['source_url'])->toBe('https://kchs.tj/node/4163')
        ->and($item['title'])->toBe('КЧС Хатлон: Спасатели спасли трёх граждан')
        ->and($item['published_at'])->toStartWith('2026-06-14T08:51:58')
        ->and($item['summary'])->toStartWith('Трое граждан оказались отрезанными водой.')
        ->and($item['cover'])->toBe('https://kchs.tj/sites/default/files/field/image/IMG_1138.jpg')
        ->and($item['gallery'])->toBe(['https://kchs.tj/sites/default/files/dop_photo/IMG_1135.jpg'])
        ->and($item['tags'])->toBe(['Новости', 'УКЧС по Хатлону'])
        ->and($item['views'])->toBe(1779)
        ->and($item['attachments'])->toBe([[
            'url' => 'https://kchs.tj/sites/default/files/otchet.pdf',
            'name' => 'otchet.pdf',
        ]]);
});

it('keeps editorial markup and drops the theme’s own', function (): void {
    Http::fake([
        'kchs.tj/node?page=0' => Http::response(listingHtml(4163)),
        'kchs.tj/node/4163' => Http::response(nodeHtml()),
    ]);

    $this->artisan('khf:scrape-source', [
        '--news' => 1,
        '--announcements' => 0,
        '--locale' => ['ru'],
    ])->assertSuccessful();

    $payload = json_decode((string) file_get_contents($this->fixturePath.'/news-ru.json'), true);
    $body = $payload['items'][0]['body_html'];

    expect($body)->toContain('<p><strong>Трое граждан оказались отрезанными водой.</strong></p>')
        // Текст ссылки остаётся, сама ссылка на чужой сайт — нет.
        ->and($body)->toContain('отчёте')
        ->and($body)->not->toContain('<a ')
        ->and($body)->not->toContain('style=')
        ->and($body)->not->toContain('class=')
        ->and($body)->not->toContain('<script')
        ->and($body)->not->toContain('<img')
        ->and($body)->not->toContain('Поделиться</div>')
        // Пустой абзац-распорка из Drupal не должен утекать в CMS.
        ->and($body)->not->toContain('<p>&nbsp;</p>');
});

it('stops at the requested number of articles', function (): void {
    Http::fake([
        'kchs.tj/node?page=0' => Http::response(listingHtml(4163, 4164, 4165)),
        'kchs.tj/node/*' => Http::response(nodeHtml()),
    ]);

    $this->artisan('khf:scrape-source', [
        '--news' => 2,
        '--announcements' => 0,
        '--locale' => ['ru'],
    ])->assertSuccessful();

    $payload = json_decode((string) file_get_contents($this->fixturePath.'/news-ru.json'), true);

    expect($payload['items'])->toHaveCount(2);
});

it('rejects an unknown locale', function (): void {
    $this->artisan('khf:scrape-source', ['--locale' => ['de']])->assertExitCode(2);

    expect(file_exists($this->fixturePath.'/news-ru.json'))->toBeFalse();
});

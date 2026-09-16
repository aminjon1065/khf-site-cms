<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

/**
 * Collects public material from the Committee's live sites — kchs.tj (Russian)
 * and khf.tj (Tajik) — into JSON fixtures under `database/seeders/data/source`.
 *
 * The two sites are independent Drupal 7 installs rather than translations of
 * each other, so each locale is scraped on its own and the seeders pair items
 * afterwards where a shared cover image proves they are the same story.
 *
 * Scraping is a deliberate, on-demand step: the fixtures are committed, and
 * seeding never depends on the network for text.
 */
class ScrapeOfficialSource extends Command
{
    protected $signature = 'khf:scrape-source
        {--news=150 : Articles to collect per source locale}
        {--announcements=40 : Announcements to collect per source locale}
        {--locale=* : Limit the run to the given source locales (ru, tg)}
        {--ca-bundle= : CA bundle path, for PHP installs without a configured one}
        {--timeout=30 : Per-request timeout in seconds}';

    protected $description = 'Scrape kchs.tj / khf.tj into the seeder fixtures the Source*Seeder classes replay';

    /**
     * @var array<string, array{base: string, announcements_path: string}>
     */
    private const SOURCES = [
        'ru' => ['base' => 'https://kchs.tj', 'announcements_path' => '/taxonomy/term/73'],
        'tg' => ['base' => 'https://khf.tj', 'announcements_path' => '/taxonomy/term/72'],
    ];

    /**
     * Listing pages hold four or five teasers each; the cap keeps a misparse
     * from walking the entire archive.
     */
    private const MAX_LISTING_PAGES = 200;

    public function handle(): int
    {
        $locales = $this->resolveLocales();

        if ($locales === []) {
            $this->components->error('Unknown locale: use --locale=ru and/or --locale=tg.');

            return self::INVALID;
        }

        $newsLimit = max(1, (int) $this->option('news'));
        $announcementLimit = max(0, (int) $this->option('announcements'));

        foreach ($locales as $locale) {
            $source = self::SOURCES[$locale];

            $this->components->info("Scraping {$source['base']} ({$locale})");

            $news = $this->collect($source['base'], '/node', $newsLimit, "news ({$locale})");
            $this->writeFixture("news-{$locale}.json", $locale, $source['base'], $news);

            if ($announcementLimit > 0) {
                $announcements = $this->collect(
                    $source['base'],
                    $source['announcements_path'],
                    $announcementLimit,
                    "announcements ({$locale})",
                );
                $this->writeFixture("announcements-{$locale}.json", $locale, $source['base'], $announcements);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveLocales(): array
    {
        /** @var array<int, string> $requested */
        $requested = (array) $this->option('locale');

        if ($requested === []) {
            return array_keys(self::SOURCES);
        }

        return array_values(array_intersect(
            array_keys(self::SOURCES),
            array_map(strtolower(...), $requested),
        ));
    }

    /**
     * Walks a Drupal listing, then fetches every teaser's full node page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collect(string $base, string $listingPath, int $limit, string $label): array
    {
        $nodeIds = $this->collectNodeIds($base, $listingPath, $limit);

        if ($nodeIds === []) {
            $this->components->warn("No articles found for {$label}.");

            return [];
        }

        $bar = $this->output->createProgressBar(count($nodeIds));
        $bar->setFormat(" {$label}: %current%/%max% [%bar%] %elapsed%");
        $bar->start();

        $items = [];

        foreach ($nodeIds as $nodeId) {
            $url = "{$base}/node/{$nodeId}";
            $html = $this->fetch($url);

            if ($html !== null) {
                $item = $this->parseArticle($html, $base, $nodeId, $url);

                if ($item !== null) {
                    $items[] = $item;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $items;
    }

    /**
     * @return array<int, int>
     */
    private function collectNodeIds(string $base, string $listingPath, int $limit): array
    {
        $ids = [];

        for ($page = 0; $page < self::MAX_LISTING_PAGES && count($ids) < $limit; $page++) {
            $html = $this->fetch("{$base}{$listingPath}?page={$page}");

            if ($html === null) {
                break;
            }

            $found = $this->articleIds($html);

            if ($found === []) {
                break;
            }

            foreach ($found as $id) {
                $ids[$id] = $id;

                if (count($ids) >= $limit) {
                    break;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<int, int>
     */
    private function articleIds(string $html): array
    {
        $xpath = $this->xpath($html);
        $ids = [];

        foreach ($this->nodes($xpath, '//article[contains(@class, "node-article")]/@id') as $attribute) {
            if (preg_match('~node-(\d+)~', (string) $attribute->nodeValue, $match) === 1) {
                $ids[] = (int) $match[1];
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseArticle(string $html, string $base, int $nodeId, string $url): ?array
    {
        $xpath = $this->xpath($html);

        $title = $this->normalizeSpace(
            $this->attribute($xpath, '//span[@property="dc:title"]/@content')
                ?? $this->text($xpath, '//h1[contains(@class, "title")]'),
        );
        $publishedAt = $this->attribute($xpath, '//span[contains(@property, "dc:date")]/@content');
        $bodyNode = $this->nodes($xpath, '//div[contains(@class, "field-name-body")]//div[@property="content:encoded"]')[0] ?? null;

        if ($title === '' || $publishedAt === null || ! $bodyNode instanceof DOMElement) {
            return null;
        }

        $summary = $this->summarize($bodyNode);
        $attachments = $this->attachments($xpath, $base);
        $body = $this->cleanBody($bodyNode, $base);

        if ($body === '') {
            return null;
        }

        return [
            'source_id' => $nodeId,
            'source_url' => $url,
            'title' => $title,
            'published_at' => Carbon::parse($publishedAt)->toIso8601String(),
            'summary' => $summary,
            'body_html' => $body,
            'cover' => $this->absolutize(
                $this->attribute($xpath, '//div[contains(@class, "field-name-field-image")]//*[@resource]/@resource'),
                $base,
            ),
            'gallery' => $this->gallery($xpath, $base),
            'tags' => $this->tags($xpath),
            'attachments' => $attachments,
            'views' => $this->views($xpath),
        ];
    }

    /**
     * Keeps the editorial markup the CMS rich-text field supports and drops
     * everything Drupal added for its own theme: classes, inline styles, share
     * widgets and empty wrappers.
     */
    private function cleanBody(DOMElement $body, string $base): string
    {
        $allowed = ['p', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'br', 'h2', 'h3', 'h4', 'blockquote', 'a'];
        $document = $body->ownerDocument;

        if (! $document instanceof DOMDocument) {
            return '';
        }

        $xpath = new DOMXPath($document);

        foreach ($this->nodes($xpath, './/script | .//style | .//iframe | .//img', $body) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach ($this->nodes($xpath, './/*', $body) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (! in_array($node->nodeName, $allowed, true)) {
                $this->unwrap($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                $node->removeAttribute($attribute->nodeName);
            }
        }

        foreach ($this->nodes($xpath, './/a', $body) as $link) {
            if ($link instanceof DOMElement) {
                $this->unwrap($link);
            }
        }

        $html = '';

        foreach ($body->childNodes as $child) {
            $html .= (string) $document->saveHTML($child);
        }

        return $this->tidy($html);
    }

    private function tidy(string $html): string
    {
        $html = preg_replace('~<(p|li|h[2-4])>(?:\s|&nbsp;)*</\1>~u', '', $html) ?? $html;
        $html = preg_replace('~\s*\R\s*~u', "\n", $html) ?? $html;

        return trim($html);
    }

    /**
     * Replaces an unsupported element with its own children, so its text stays.
     */
    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if (! $parent instanceof DOMNode) {
            return;
        }

        while ($node->firstChild instanceof DOMNode) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function summarize(DOMElement $body): string
    {
        return Str::limit($this->normalizeSpace($body->textContent), 240);
    }

    /**
     * @return array<int, string>
     */
    private function gallery(DOMXPath $xpath, string $base): array
    {
        $urls = [];

        foreach ($this->nodes($xpath, '//div[contains(@class, "galleria-content")]//a/@rel') as $attribute) {
            $url = $this->absolutize((string) $attribute->nodeValue, $base);

            if ($url !== null) {
                $urls[$url] = $url;
            }
        }

        return array_values($urls);
    }

    /**
     * @return array<int, string>
     */
    private function tags(DOMXPath $xpath): array
    {
        $tags = [];

        foreach ($this->nodes($xpath, '//div[contains(@class, "field-name-field-tags")]//a') as $node) {
            $tag = $this->normalizeSpace($node->textContent);

            if ($tag !== '') {
                $tags[$tag] = $tag;
            }
        }

        return array_values($tags);
    }

    /**
     * @return array<int, array{url: string, name: string}>
     */
    private function attachments(DOMXPath $xpath, string $base): array
    {
        $files = [];

        foreach ($this->nodes($xpath, '//div[contains(@class, "field-name-body")]//a/@href') as $attribute) {
            $href = (string) $attribute->nodeValue;

            if (preg_match('~\.(pdf|docx?|xlsx?)($|\?)~i', $href) !== 1) {
                continue;
            }

            $url = $this->absolutize($href, $base);

            if ($url === null) {
                continue;
            }

            $files[$url] = [
                'url' => $url,
                'name' => urldecode(basename((string) parse_url($url, PHP_URL_PATH))),
            ];
        }

        return array_values($files);
    }

    private function views(DOMXPath $xpath): int
    {
        return (int) preg_replace('~\D~', '', $this->text($xpath, '//li[contains(@class, "statistics_counter")]'));
    }

    private function absolutize(?string $url, string $base): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://', 'mailto:', 'tel:'])) {
            return $url;
        }

        return $base.'/'.ltrim($url, '/');
    }

    private function attribute(DOMXPath $xpath, string $query): ?string
    {
        $node = $this->nodes($xpath, $query)[0] ?? null;

        return $node === null ? null : trim((string) $node->nodeValue);
    }

    private function text(DOMXPath $xpath, string $query): string
    {
        $node = $this->nodes($xpath, $query)[0] ?? null;

        return $node === null ? '' : $this->normalizeSpace($node->textContent);
    }

    private function normalizeSpace(?string $value): string
    {
        return trim((string) preg_replace('~[\s\x{00a0}]+~u', ' ', (string) $value));
    }

    /**
     * Runs an XPath query and hands back plain nodes: no `false` on a bad
     * expression, and no namespace nodes, which the rest of the parser has no
     * use for.
     *
     * @return array<int, DOMNode>
     */
    private function nodes(DOMXPath $xpath, string $query, ?DOMNode $context = null): array
    {
        $list = $context instanceof DOMNode ? $xpath->query($query, $context) : $xpath->query($query);

        if ($list === false) {
            return [];
        }

        $nodes = [];

        foreach ($list as $node) {
            if ($node instanceof DOMNode) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = $this->client()->get($url);
        } catch (Throwable $exception) {
            $this->components->warn("{$url}: {$exception->getMessage()}");

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    private function client(): PendingRequest
    {
        // Флаг перекрывает конфиг, конфиг — значение по умолчанию: на машинах
        // без настроенного CA-бандла (`curl.cainfo` пуст) путь уже лежит в
        // SEED_SOURCE_CA_BUNDLE, и передавать его руками незачем.
        $caBundle = (string) ($this->option('ca-bundle') ?: config('seeding.source.ca_bundle', ''));

        return Http::withHeaders([
            'User-Agent' => 'khf-site-cms seed fixtures (+https://khf.tj)',
            'Accept-Language' => 'ru,tg;q=0.9',
        ])
            ->timeout(max(5, (int) $this->option('timeout')))
            ->retry(2, 500, throw: false)
            ->when(
                $caBundle !== '',
                fn (PendingRequest $request): PendingRequest => $request->withOptions(['verify' => $caBundle]),
            );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws JsonException
     */
    private function writeFixture(string $file, string $locale, string $base, array $items): void
    {
        $directory = (string) config('seeding.source.path');

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $payload = [
            'source' => $base,
            'locale' => $locale,
            'scraped_at' => now()->toIso8601String(),
            'items' => $items,
        ];

        file_put_contents(
            "{$directory}/{$file}",
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n",
        );

        $this->components->info(count($items)." item(s) → database/seeders/data/source/{$file}");
    }
}

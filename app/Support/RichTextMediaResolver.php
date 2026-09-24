<?php

namespace App\Support;

use App\Models\MediaAsset;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RichTextMediaResolver
{
    public function resolve(string $html): string
    {
        if ($html === '' || ! str_contains($html, 'data-media-id')) {
            return $html;
        }

        $parsed = $this->parse($html);

        if ($parsed === null) {
            return $html;
        }

        [$document, $root, $images] = $parsed;

        foreach ($images as [$image, $media]) {
            if ($media instanceof Media) {
                $this->resolveImage($document, $image, $media);
            }
        }

        $resolved = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $resolved .= $document->saveHTML($child);
        }

        return $resolved;
    }

    /**
     * Pictures in a text, and how many of them tell readers who can't see
     * them nothing: no description in the text or in the media library, and
     * not marked decorative in either.
     *
     * @return array{images: int, undescribed: int}
     */
    public function descriptions(string $html): array
    {
        $parsed = $html === '' || ! str_contains($html, '<img') ? null : $this->parse($html);

        if ($parsed === null) {
            return ['images' => 0, 'undescribed' => 0];
        }

        $undescribed = 0;

        foreach ($parsed[2] as [$image, $media]) {
            if (! $this->isDecorative($image, $media) && $this->description($image, $media) === '') {
                $undescribed++;
            }
        }

        return ['images' => count($parsed[2]), 'undescribed' => $undescribed];
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement, 2: list<array{0: DOMElement, 1: Media|null}>}|null
     */
    private function parse(string $html): ?array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="rich-media-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $root = $document->getElementById('rich-media-root');
        if (! $root instanceof DOMElement) {
            return null;
        }

        $found = [];
        $mediaIds = [];
        foreach ($root->getElementsByTagName('img') as $image) {
            $mediaId = filter_var($image->getAttribute('data-media-id'), FILTER_VALIDATE_INT);
            $mediaId = is_int($mediaId) && $mediaId > 0 ? $mediaId : null;

            $found[] = [$image, $mediaId];

            if ($mediaId !== null) {
                $mediaIds[] = $mediaId;
            }
        }

        /** @var Collection<int, Media> $mediaItems */
        $mediaItems = $mediaIds === []
            ? collect()
            : Media::query()
                ->with('model')
                ->whereKey(array_values(array_unique($mediaIds)))
                ->get()
                ->keyBy(fn (Media $media): int => (int) $media->getKey());

        $images = [];
        foreach ($found as [$image, $mediaId]) {
            $images[] = [$image, $mediaId === null ? null : $mediaItems->get($mediaId)];
        }

        return [$document, $root, $images];
    }

    /**
     * What the picture says to readers who can't see it: the description
     * written in the text, else the one in the media library. HTMLPurifier
     * fills a missing alt with the file name — «IMG_2034.jpg» describes
     * nothing, so it counts as missing.
     */
    private function description(DOMElement $image, ?Media $media): string
    {
        if ($this->isDecorative($image, $media)) {
            return '';
        }

        $alt = trim($image->getAttribute('alt'));

        if ($alt !== '' && ! $this->looksLikeFileName($alt, $image, $media)) {
            return $alt;
        }

        $asset = $media?->model;

        return $asset instanceof MediaAsset ? trim((string) $asset->alt) : '';
    }

    /**
     * A decorative picture needs no description: the editor marked it so in
     * the text (`data-decorative`) or in the media library.
     */
    private function isDecorative(DOMElement $image, ?Media $media): bool
    {
        if ($image->hasAttribute('data-decorative')) {
            return true;
        }

        $asset = $media?->model;

        return $asset instanceof MediaAsset && $asset->is_decorative;
    }

    private function looksLikeFileName(string $alt, DOMElement $image, ?Media $media): bool
    {
        $name = mb_strtolower($alt);
        $path = parse_url($image->getAttribute('src'), PHP_URL_PATH);

        return (is_string($path) && $name === mb_strtolower(basename($path)))
            || ($media !== null && $name === mb_strtolower((string) $media->file_name))
            || preg_match('/\.(jpe?g|png|webp|gif|avif|heic|bmp|tiff?)$/iu', $alt) === 1;
    }

    private function resolveImage(
        DOMDocument $document,
        DOMElement $image,
        Media $media,
    ): void {
        $image->setAttribute('alt', $this->description($image, $media));

        $imageData = PublicImageData::fromMedia(
            $media,
            $image->getAttribute('alt'),
        );
        $fallback = $imageData['sources']['fallback'];

        if ($fallback === []) {
            $placeholder = $imageData['placeholder']['data_url'] ?? null;
            if (is_string($placeholder)) {
                $image->setAttribute('src', $placeholder);
                $image->removeAttribute('srcset');
            }

            return;
        }

        $image->setAttribute('src', $fallback[array_key_last($fallback)]['url']);
        $image->setAttribute('srcset', $this->srcset($fallback));
        $image->setAttribute('sizes', $image->getAttribute('sizes') ?: '100vw');
        $image->setAttribute('loading', $image->getAttribute('loading') ?: 'lazy');
        $image->setAttribute('decoding', $image->getAttribute('decoding') ?: 'async');

        $parent = $image->parentNode;
        if (! $parent instanceof DOMElement || $parent->tagName !== 'picture') {
            $picture = $document->createElement('picture');
            $picture->setAttribute('data-media-id', (string) $media->getKey());
            $parent?->insertBefore($picture, $image);
            $picture->appendChild($image);
        } else {
            $picture = $parent;
            foreach (iterator_to_array($picture->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->tagName === 'source') {
                    $picture->removeChild($child);
                }
            }
        }

        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $mimeType) {
            $sources = $imageData['sources'][$format];
            if ($sources === []) {
                continue;
            }

            $source = $document->createElement('source');
            $source->setAttribute('type', $mimeType);
            $source->setAttribute('srcset', $this->srcset($sources));
            $source->setAttribute('sizes', $image->getAttribute('sizes'));
            $picture->insertBefore($source, $image);
        }
    }

    /**
     * @param  list<array{url: string, width: int, height: int|null, bytes: int}>  $sources
     */
    private function srcset(array $sources): string
    {
        return implode(', ', array_map(
            fn (array $source): string => "{$source['url']} {$source['width']}w",
            $sources,
        ));
    }
}

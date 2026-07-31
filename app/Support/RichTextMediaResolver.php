<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RichTextMediaResolver
{
    public function resolve(string $html): string
    {
        if ($html === '' || ! str_contains($html, 'data-media-id')) {
            return $html;
        }

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
            return $html;
        }

        $images = [];
        $mediaIds = [];
        foreach ($root->getElementsByTagName('img') as $image) {
            $mediaId = filter_var($image->getAttribute('data-media-id'), FILTER_VALIDATE_INT);
            if (! is_int($mediaId) || $mediaId < 1) {
                continue;
            }

            $images[] = [$image, $mediaId];
            $mediaIds[] = $mediaId;
        }

        $mediaItems = Media::query()
            ->whereKey(array_values(array_unique($mediaIds)))
            ->get()
            ->keyBy(fn (Media $media): int => (int) $media->getKey());

        foreach ($images as [$image, $mediaId]) {
            $media = $mediaItems->get($mediaId);
            if (! $media instanceof Media) {
                continue;
            }

            $this->resolveImage($document, $image, $media);
        }

        $resolved = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $resolved .= $document->saveHTML($child);
        }

        return $resolved;
    }

    private function resolveImage(
        DOMDocument $document,
        DOMElement $image,
        Media $media,
    ): void {
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

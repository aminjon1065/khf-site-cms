<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Public media keeps normal CDN/storage URLs. Private workflow media receives
 * a short-lived signed CMS preview URL that is also permission checked.
 */
class PrivateAwareMediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if (! $this->isPrivate()) {
            return parent::getUrl();
        }

        return $this->signedUrl(now()->addMinutes(5));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        if (! $this->isPrivate()) {
            return parent::getTemporaryUrl($expiration, $options);
        }

        return $this->signedUrl($expiration);
    }

    private function isPrivate(): bool
    {
        return $this->getDiskName() === config('media-library.private_disk_name', 'content_private');
    }

    private function signedUrl(DateTimeInterface $expiration): string
    {
        return URL::temporarySignedRoute('media.private', $expiration, [
            'media' => $this->media->getKey(),
            'conversion' => $this->conversion?->getName() ?? 'original',
        ]);
    }
}

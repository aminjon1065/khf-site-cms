<?php

namespace App\Jobs;

use App\Services\AvifDerivativeGenerator;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class PerformMediaConversions extends PerformConversionsJob
{
    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  ConversionCollection<array-key, Conversion>  $conversions
     */
    public function __construct(
        ConversionCollection $conversions,
        Media $media,
        bool $onlyMissing = false,
    ) {
        parent::__construct($conversions, $media, $onlyMissing);

        $this->setStatus('pending');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('media-conversions:'.$this->media->getKey()))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(
        FileManipulator $fileManipulator,
        ?AvifDerivativeGenerator $avifDerivativeGenerator = null,
    ): bool {
        $this->setStatus('processing');

        try {
            $avifDerivativeGenerator ??= app(AvifDerivativeGenerator::class);
            $standardConversions = $this->conversions->reject(
                fn (Conversion $conversion): bool => $avifDerivativeGenerator->handles($conversion),
            );
            $avifConversions = $this->conversions->filter(
                fn (Conversion $conversion): bool => $avifDerivativeGenerator->handles($conversion),
            );

            $fileManipulator->performConversions(
                $standardConversions,
                $this->media,
                $this->onlyMissing,
            );
            $avifDerivativeGenerator->generate(
                $this->media,
                $avifConversions,
                $this->onlyMissing,
            );
            $this->setStatus('ready');

            return true;
        } catch (Throwable $exception) {
            $this->setStatus('failed', $exception->getMessage());

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?? 'Неизвестная ошибка обработки.';
        $this->setStatus('failed', $message);

        Log::error('Media conversions failed.', [
            'media_id' => $this->media->getKey(),
            'error' => $message,
        ]);
    }

    private function setStatus(string $status, ?string $error = null): void
    {
        if (! $this->media->exists) {
            return;
        }

        $this->media
            ->setCustomProperty('conversion_status', $status)
            ->setCustomProperty(
                'conversion_error',
                $error === null ? null : Str::limit($error, 497),
            )
            ->saveQuietly();
    }
}

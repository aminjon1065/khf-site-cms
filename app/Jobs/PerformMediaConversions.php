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
        // Поднимаем предел на время задачи: расход GD накапливается в процессе
        // воркера, и на исходных 128M конверсия падала фатально — молча, без
        // записи в failed_jobs. См. config/media-library.php.
        $this->raiseMemoryLimit();

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

    /**
     * Поднимает предел памяти процесса, если настроенный больше текущего.
     * Понижать нельзя: воркер может быть запущен с осознанно большим лимитом.
     */
    private function raiseMemoryLimit(): void
    {
        $configured = (string) config('media-library.conversion_memory_limit');

        if ($configured === '') {
            return;
        }

        $current = (string) ini_get('memory_limit');

        if ($current === '-1' || $this->toBytes($configured) <= $this->toBytes($current)) {
            return;
        }

        ini_set('memory_limit', $configured);
    }

    /**
     * Значение вида `512M` в байтах. `-1` (без предела) считаем бесконечностью.
     */
    private function toBytes(string $value): float
    {
        if ($value === '-1') {
            return INF;
        }

        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
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

<?php

namespace App\Jobs;

use App\Support\FrontendRevalidation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RevalidateFrontend implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 10;

    public int $uniqueFor = 30;

    /** @var list<int> */
    public array $backoff = [10, 30, 120];

    /**
     * @param  list<string>  $locales
     */
    public function __construct(
        public string $type,
        public ?int $id,
        public ?string $slug,
        public array $locales,
        public string $event,
    ) {
        $this->onQueue((string) config('queue.names.revalidation'));
    }

    /**
     * @return list<ThrottlesExceptions>
     */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(3, 300))
                ->by('frontend-revalidation')
                ->backoff(1)
                ->report(),
        ];
    }

    public function uniqueId(): string
    {
        return hash('sha256', implode('|', $this->tags()));
    }

    public function handle(): void
    {
        $url = (string) config('services.frontend.revalidation_url');
        $secret = (string) config('services.frontend.revalidation_secret');

        if ($url === '' || $secret === '') {
            return;
        }

        try {
            Http::withToken($secret)
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(5)
                ->post($url, $this->payload())
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            if (config('queue.default') !== 'sync') {
                throw $exception;
            }

            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('Frontend cache revalidation failed permanently.', [
            'content_type' => $this->type,
            'content_id' => $this->id,
            'slug' => $this->slug,
            'locales' => $this->locales,
            'event' => $this->event,
            'tags' => $this->tags(),
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array{
     *     type: string,
     *     id: int|null,
     *     slug: string|null,
     *     locales: list<string>,
     *     event: string,
     *     tags: list<string>
     * }
     */
    public function payload(): array
    {
        return FrontendRevalidation::payload(
            type: $this->type,
            id: $this->id,
            slug: $this->slug,
            locales: $this->locales,
            event: $this->event,
        );
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->payload()['tags'];
    }
}

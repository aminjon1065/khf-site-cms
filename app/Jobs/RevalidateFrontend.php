<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class RevalidateFrontend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 120];

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
                ->timeout(5)
                ->post($url, ['tag' => 'cms'])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            report($e);

            // Under the `sync` connection this job runs inline inside the
            // editor's publish request, so a frontend outage must not turn
            // content publication into a 500 (P0-1). On a real queue
            // (database/redis) re-throw so the worker's own tries/backoff
            // retries the job as configured.
            if ($this->job !== null && $this->job->getConnectionName() !== 'sync') {
                throw $e;
            }
        }
    }
}

<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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

        Http::withToken($secret)
            ->acceptJson()
            ->timeout(5)
            ->post($url, ['tag' => 'cms'])
            ->throw();
    }
}

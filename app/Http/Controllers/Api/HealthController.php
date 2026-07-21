<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function health(): JsonResponse
    {
        $database = $this->databaseAvailable();

        return response()->json([
            'status' => $database ? 'ok' : 'unavailable',
            'service' => 'khf-cms-api',
            'version' => 'v1',
            'checks' => ['database' => $database],
            'timestamp' => now()->toIso8601String(),
        ], $database ? 200 : 503);
    }

    public function ready(): JsonResponse
    {
        $lastRun = Cache::get('health.scheduler.last_run');
        $scheduler = is_string($lastRun)
            && Carbon::parse($lastRun)->isAfter(now()->subMinutes(15));
        $checks = [
            'database' => $this->databaseAvailable(),
            'storage' => is_dir(storage_path('app')) && is_writable(storage_path('app')),
            'scheduler' => $scheduler,
            'queue_connection' => (string) config('queue.default'),
        ];
        $ready = $checks['database'] && $checks['storage'] && $checks['scheduler'];

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'scheduler_last_run' => $lastRun,
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    private function databaseAvailable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HomePageReadModel;
use App\Services\PublicReadModelCache;
use Illuminate\Http\JsonResponse;

/**
 * Home-page composition endpoint. Returns the enabled home blocks (in editor
 * order, with their per-block limits) plus the denormalized data each block
 * renders — a single request that fully drives the public home page.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly HomePageReadModel $readModel,
        private readonly PublicReadModelCache $cache,
    ) {}

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $data = $this->cache->remember(
            PublicReadModelCache::HOME,
            $locale,
            fn (): array => $this->readModel->build($locale),
        );

        return response()->json(['data' => $data]);
    }
}

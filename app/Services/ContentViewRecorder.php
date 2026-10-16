<?php

namespace App\Services;

use App\Support\ContentTypes;
use Illuminate\Support\Facades\DB;

/**
 * Adds the site's page views to today's sums (content_views). Only public
 * materials are counted; an unknown or unpublished address is dropped, not
 * an error — the site may be a minute behind the CMS.
 */
class ContentViewRecorder
{
    /**
     * Types with a public page of their own (documents have none).
     *
     * @var list<string>
     */
    public const TYPES = ['news', 'alert', 'instruction', 'project', 'announcement', 'page'];

    /**
     * @param  list<array{type: string, slug: string, locale: string, count: int}>  $views
     * @return int Views recorded
     */
    public function record(array $views): int
    {
        $sums = [];

        foreach (collect($views)->groupBy('type') as $type => $items) {
            $class = ContentTypes::MAP[(string) $type];
            $ids = $class::query()
                ->public()
                ->whereIn('slug', $items->pluck('slug')->unique()->values()->all())
                ->pluck('id', 'slug');

            foreach ($items as $item) {
                $id = $ids->get($item['slug']);

                if ($id === null) {
                    continue;
                }

                $key = "{$type}|{$id}|{$item['locale']}";
                $sums[$key] = [
                    'type' => (string) $type,
                    'id' => (int) $id,
                    'locale' => $item['locale'],
                    'count' => ($sums[$key]['count'] ?? 0) + $item['count'],
                ];
            }
        }

        if ($sums === []) {
            return 0;
        }

        $date = now()->toDateString();

        DB::transaction(function () use ($sums, $date): void {
            foreach ($sums as $sum) {
                $row = [
                    'content_type' => $sum['type'],
                    'content_id' => $sum['id'],
                    'date' => $date,
                    'locale' => $sum['locale'],
                ];

                DB::table('content_views')->insertOrIgnore([...$row, 'views' => 0]);
                DB::table('content_views')->where($row)->increment('views', $sum['count']);

                // The total in the news list. Through the query builder:
                // a view must not touch updated_at — it is the version the
                // editor's conflict check compares.
                if ($sum['type'] === 'news') {
                    DB::table('news')->where('id', $sum['id'])->increment('views_count', $sum['count']);
                }
            }
        });

        return array_sum(array_column($sums, 'count'));
    }
}

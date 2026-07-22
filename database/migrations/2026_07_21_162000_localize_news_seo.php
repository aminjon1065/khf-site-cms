<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('news')->select(['id', 'seo'])->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $seo = $this->decode($row->seo);

                if (isset($seo['ru']) || isset($seo['tg']) || isset($seo['en'])) {
                    continue;
                }

                DB::table('news')->where('id', $row->id)->update([
                    'seo' => json_encode([
                        'ru' => [
                            'title' => (string) ($seo['title'] ?? ''),
                            'description' => (string) ($seo['description'] ?? ''),
                        ],
                        'tg' => ['title' => '', 'description' => ''],
                        'en' => ['title' => '', 'description' => ''],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('news')->select(['id', 'seo'])->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $seo = $this->decode($row->seo);
                $ru = is_array($seo['ru'] ?? null) ? $seo['ru'] : [];

                DB::table('news')->where('id', $row->id)->update([
                    'seo' => json_encode([
                        'title' => (string) ($ru['title'] ?? ''),
                        'description' => (string) ($ru['description'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};

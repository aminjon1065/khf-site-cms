<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Текстовое описание территории» becomes trilingual like the rest of an
 * alert's text (owner decision, 2026-10-16): the site shows it on each
 * language version that has it. The notes written so far are Russian —
 * the wizard and its operators work in Russian — so they become the Russian
 * version. Proposals waiting for approval carry the raw column value, and
 * are converted too: applying one must not write plain text into JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('alerts')->where('territory_note', '')->update(['territory_note' => null]);

        DB::table('alerts')->whereNotNull('territory_note')->select(['id', 'territory_note'])->lazyById()
            ->each(function (object $row): void {
                DB::table('alerts')->where('id', $row->id)->update([
                    'territory_note' => $this->asRussian((string) $row->territory_note),
                ]);
            });

        Schema::table('alerts', function (Blueprint $table): void {
            $table->json('territory_note')->nullable()->change();
        });

        $this->convertPendingChanges(fn (string $note): string => $this->asRussian($note));
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->text('territory_note')->nullable()->change();
        });

        DB::table('alerts')->whereNotNull('territory_note')->select(['id', 'territory_note'])->lazyById()
            ->each(function (object $row): void {
                DB::table('alerts')->where('id', $row->id)->update([
                    'territory_note' => $this->firstTranslation((string) $row->territory_note),
                ]);
            });

        $this->convertPendingChanges(fn (string $note): ?string => $this->firstTranslation($note));
    }

    private function asRussian(string $note): string
    {
        return json_encode(['ru' => $note], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function firstTranslation(string $json): ?string
    {
        $translations = json_decode($json, true);

        if (! is_array($translations)) {
            return $json;
        }

        foreach (['ru', 'tg', 'en'] as $locale) {
            if (is_string($translations[$locale] ?? null) && trim($translations[$locale]) !== '') {
                return $translations[$locale];
            }
        }

        return null;
    }

    /**
     * @param  callable(string): ?string  $convert
     */
    private function convertPendingChanges(callable $convert): void
    {
        DB::table('pending_changes')
            ->where('changeable_type', 'App\\Models\\Alert')
            ->where('status', 'pending')
            ->select(['id', 'changes'])
            ->lazyById()
            ->each(function (object $row) use ($convert): void {
                $changes = json_decode((string) $row->changes, true);

                if (! is_array($changes) || ! is_string($changes['territory_note'] ?? null)) {
                    return;
                }

                $changes['territory_note'] = $convert($changes['territory_note']);

                DB::table('pending_changes')->where('id', $row->id)->update([
                    'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('id');
            $table->string('application_url', 2048)->nullable()->after('deadline');
        });

        $used = [];
        DB::table('announcements')->orderBy('id')->get(['id', 'title'])->each(
            function (object $row) use (&$used): void {
                $titles = json_decode((string) $row->title, true);
                $source = is_array($titles)
                    ? (string) ($titles['ru'] ?? $titles['tg'] ?? $titles['en'] ?? '')
                    : '';
                $base = Str::slug($source, '-', 'ru') ?: 'announcement-'.$row->id;
                $slug = $base;
                $suffix = 2;

                while (isset($used[$slug])) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                $used[$slug] = true;
                DB::table('announcements')->where('id', $row->id)->update(['slug' => $slug]);
            },
        );

        Schema::table('announcements', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'application_url']);
        });
    }
};

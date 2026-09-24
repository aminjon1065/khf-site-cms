<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the text of a published announcement or alert last really changed —
 * the date sitemap.xml gives search engines, as for news, pages, projects and
 * instructions. updated_at can't say it: it moves on any save, an alert's
 * «ends soon» notice included. Unknown for existing materials — left empty
 * rather than guessed.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = ['announcements', 'alerts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->timestamp('content_updated_at')->nullable()->after('published_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('content_updated_at');
            });
        }
    }
};

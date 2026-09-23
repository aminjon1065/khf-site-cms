<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the text of a published material last really changed (A-2 of the site
 * contract: NewsArticle.dateModified, the page's modified time). The row's
 * updated_at can't say it: it moves on any save, including a view counter
 * or a status change. Unknown for existing materials — left empty rather
 * than guessed.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = ['news', 'pages', 'projects', 'instructions'];

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

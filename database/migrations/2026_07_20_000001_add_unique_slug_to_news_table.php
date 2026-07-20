<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes news.slug from a plain index to a unique constraint. Slugs are the
 * public join key for /news/{slug}; uniqueness is enforced at the DB level in
 * addition to News::uniqueSlug() collision handling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->dropIndex(['slug']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->index('slug');
        });
    }
};

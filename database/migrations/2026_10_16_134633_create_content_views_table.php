<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Views of public pages, as daily sums per material and site language
 * (owner decision, 2026-10-16: our own statistics, not Google Analytics).
 * Nothing about the reader is stored — no IP, no cookie, no device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_views', function (Blueprint $table): void {
            $table->id();
            // App\Support\ContentTypes key: news, alert, instruction, …
            $table->string('content_type', 20);
            $table->unsignedBigInteger('content_id');
            $table->date('date');
            // Site locale of the page: ru, tj, en.
            $table->string('locale', 2);
            $table->unsignedInteger('views')->default(0);

            $table->unique(['content_type', 'content_id', 'date', 'locale']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_views');
    }
};

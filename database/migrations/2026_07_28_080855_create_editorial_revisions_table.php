<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('editorial_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 32);
            $table->unsignedBigInteger('content_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('draft_key');
            $table->json('data');
            $table->string('base_version')->nullable();
            $table->string('source', 24)->default('autosave');
            $table->timestamps();

            $table->index(['content_type', 'content_id', 'created_at'], 'editorial_revisions_content_history');
            $table->index(['draft_key', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_revisions');
    }
};

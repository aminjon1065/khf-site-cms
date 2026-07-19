<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->json('title');            // translatable
            $table->json('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('slug')->nullable()->index();

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();

            $table->string('cover_alt')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('show_on_home')->default(true);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->json('seo')->nullable();

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};

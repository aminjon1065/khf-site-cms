<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');            // translatable
            $table->json('body')->nullable();
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->json('title');            // translatable
            $table->json('body')->nullable();
            $table->string('kind')->default('vacancy'); // vacancy | tender
            $table->date('deadline')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->json('title');            // translatable
            $table->json('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->json('label');            // translatable
            $table->string('url')->nullable();
            $table->string('location')->default('main'); // main | footer
            $table->foreignId('parent_id')->nullable()->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('home_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type');           // hero_alert | news | instructions | ...
            $table->json('title')->nullable(); // translatable
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_blocks');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('pages');
    }
};

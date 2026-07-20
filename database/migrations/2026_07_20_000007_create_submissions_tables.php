<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citizen submissions (electronic reception) and their internal comments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->nullable()->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('topic')->nullable()->index();
            $table->text('message');
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('consent')->default(false);
            $table->string('status')->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('submission_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_comments');
        Schema::dropIfExists('submissions');
    }
};

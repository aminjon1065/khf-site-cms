<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructions', function (Blueprint $table) {
            $table->id();
            $table->json('name');             // translatable
            $table->json('summary')->nullable();
            $table->string('slug')->nullable()->index();
            $table->string('hazard_type')->nullable()->index();

            // structured sections: {before:[], during:[], after:[], prohibited:[]} per locale
            $table->json('sections')->nullable();

            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('alert_instruction', function (Blueprint $table) {
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instruction_id')->constrained()->cascadeOnDelete();
            $table->primary(['alert_id', 'instruction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_instruction');
        Schema::dropIfExists('instructions');
    }
};

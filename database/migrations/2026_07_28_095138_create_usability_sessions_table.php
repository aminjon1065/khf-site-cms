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
        Schema::create('usability_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('participant_code', 24)->unique();
            $table->string('role', 50);
            $table->string('experience_level', 20);
            $table->json('tasks');
            $table->json('sus_responses');
            $table->decimal('sus_score', 5, 2);
            $table->text('notes')->nullable();
            $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['completed_at', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usability_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes to a published (or scheduled) material proposed by someone without
 * the publish permission: the live version stays on the site until an
 * approver applies them (owner decision, 2026-09-23 — official materials go
 * through approval when the role can't publish).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_changes', function (Blueprint $table): void {
            $table->id();
            $table->morphs('changeable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Raw column values the change sets, exactly as they will be
            // written (translatable columns hold their JSON).
            $table->json('changes');
            // Relation id lists the change replaces (news tags, alert regions…).
            $table->json('relations')->nullable();
            $table->timestamp('base_updated_at')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('comment')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['changeable_type', 'changeable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_changes');
    }
};

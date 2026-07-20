<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds editorial ordering + a "pinned/priority" flag to instructions (the
 * public catalog is sorted and the featured tiles are driven by is_priority),
 * and promotes slug to a unique constraint (the public /guides/{slug} join key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructions', function (Blueprint $table): void {
            $table->boolean('is_priority')->default(false)->after('hazard_type');
            $table->unsignedSmallInteger('sort')->default(0)->after('is_priority');

            $table->dropIndex(['slug']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('instructions', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->index('slug');
            $table->dropColumn(['is_priority', 'sort']);
        });
    }
};

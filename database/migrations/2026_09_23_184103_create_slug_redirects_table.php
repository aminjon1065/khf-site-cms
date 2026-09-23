<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Former addresses of materials. When a slug changes, links already shared
 * keep working: the public API answers the old address with a permanent
 * redirect to the material's current one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->morphs('redirectable');
            $table->string('old_slug');
            $table->timestamps();

            // An address leads to one material of a type: the latest owner.
            $table->unique(['redirectable_type', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};

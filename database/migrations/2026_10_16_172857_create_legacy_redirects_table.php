<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses of the Committee's previous sites (Drupal: khf.tj in Tajik,
 * kchs.tj in Russian) that lead to a material now in the CMS. Old links live
 * in decrees, other agencies' sites and search results; without this table
 * every one of them breaks the day khf.tj switches to the new site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_redirects', function (Blueprint $table) {
            $table->id();
            // Host and path, as `khf.tj/node/6112`: node numbers of the two
            // old sites overlap, so the host is part of the address.
            $table->string('source')->unique();
            $table->morphs('redirectable');
            // The language version the old page was in.
            $table->string('locale', 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_redirects');
    }
};

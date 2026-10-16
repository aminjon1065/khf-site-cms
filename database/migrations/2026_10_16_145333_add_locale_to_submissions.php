<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language the appeal was written on the site in (tg, ru, en): the
 * email about a change of its status goes out in that language. Appeals
 * sent before get Russian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('locale', 2)->nullable()->after('region_id');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};

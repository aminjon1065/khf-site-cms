<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructions', function (Blueprint $table): void {
            // Optional rich-text detail body (translatable JSON), shown under
            // the structured steps on the public guide page.
            $table->json('body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instructions', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }
};

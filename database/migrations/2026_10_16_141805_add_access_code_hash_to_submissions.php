<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The citizen checks the status of an appeal on the site by its number and a
 * check code given with it. The number is sequential and easy to guess, so
 * only the code proves the appeal is theirs; it is stored as a hash. Appeals
 * sent before have no code and are checked by phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('access_code_hash')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('access_code_hash');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The electronic reception takes a phone number or an email: most people
 * in the regions answer the phone, not mail. Existing rows keep their email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('submissions')->whereNull('email')->update(['email' => '']);

        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }
};

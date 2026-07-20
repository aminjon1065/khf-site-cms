<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches a region into a full regional-management unit record so the public
 * "Региональные управления" directory can be served from the CMS: the office
 * name (translatable), postal address (translatable) and contact e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->json('head')->nullable()->after('name');               // translatable office name
            $table->json('address')->nullable()->after('regional_center'); // translatable postal address
            $table->string('email')->nullable()->after('duty_phone');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['head', 'address', 'email']);
        });
    }
};

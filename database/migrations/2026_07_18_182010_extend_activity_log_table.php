<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->boolean('is_critical')->default(false)->index()->after('event');
            $table->string('ip_address', 45)->nullable()->after('is_critical');
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->string('location')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn(['is_critical', 'ip_address', 'user_agent', 'location']);
        });
    }
};

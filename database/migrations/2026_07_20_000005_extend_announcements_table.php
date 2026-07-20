<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes announcements to a full editorial content type: an issuing
 * subdivision/project (`org`) and soft deletes. Editorial status + publish
 * timestamp already exist on the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('org')->nullable()->after('kind');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn(['org', 'deleted_at']);
        });
    }
};

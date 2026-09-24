<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles the administrator builds need a name people read and a line on what
 * the role is for; `name` stays the fixed key the code and the seeders use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }

    private function table(): string
    {
        return (string) config('permission.table_names.roles', 'roles');
    }
};

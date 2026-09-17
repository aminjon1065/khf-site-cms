<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A unit may sit under another one (a main directorate and its
     * departments, at any depth). Deleting a unit that still has subunits is
     * refused by the database itself, so nothing disappears by accident.
     */
    public function up(): void
    {
        Schema::table('structure_units', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('structure_units')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structure_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};

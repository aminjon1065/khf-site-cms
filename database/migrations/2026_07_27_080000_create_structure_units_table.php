<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_units', function (Blueprint $table) {
            $table->id();
            $table->string('num');                      // display number, e.g. "01" — independent of $sort
            $table->json('name');                        // translatable
            $table->json('desc');                          // translatable
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_units');
    }
};

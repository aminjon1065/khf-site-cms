<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->json('name');                     // translatable
            $table->string('code')->unique();         // dushanbe, sughd, khatlon, gbao, rrp
            $table->string('type');                   // RegionType
            $table->string('regional_center')->nullable();
            $table->string('phone')->nullable();
            $table->string('duty_phone')->nullable();
            $table->unsignedSmallInteger('districts_count')->default(0);
            $table->string('status')->default('normal'); // normal | attention | warning
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->json('name');                     // translatable
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaders', function (Blueprint $table) {
            $table->id();
            $table->json('role');                       // translatable: "Председатель Комитета", "Первый заместитель председателя"...
            $table->json('name');                        // translatable
            $table->json('meta')->nullable();             // translatable: rank + tenure ("Генерал-лейтенант · с 2016 года")
            $table->json('bio')->nullable();               // translatable
            $table->boolean('is_chairman')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaders');
    }
};

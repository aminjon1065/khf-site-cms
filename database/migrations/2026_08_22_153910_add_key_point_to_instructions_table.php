<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Главное за 10 секунд» — самое важное действие по инструкции.
 *
 * Блок на странице инструкции существовал, но под этим заголовком выводился
 * `summary` — однострочное описание темы из каталожной плитки. Для человека в
 * опасности это разные тексты: «Как действовать при угрозе селевого потока»
 * не говорит, что делать прямо сейчас.
 *
 * Переводимое поле (json), как `name`, `summary` и `body` рядом: текст читают
 * на языке страницы, и подставлять русский на /tj недопустимо — это инструкция
 * по спасению жизни.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructions', function (Blueprint $table): void {
            $table->json('key_point')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('instructions', function (Blueprint $table): void {
            $table->dropColumn('key_point');
        });
    }
};

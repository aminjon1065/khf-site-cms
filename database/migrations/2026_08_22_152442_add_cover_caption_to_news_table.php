<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подпись под фотографией новости.
 *
 * Публичная часть выводила подпись из статического словаря, поэтому у всех
 * материалов она была одна и та же («Фото: вертолёт КЧС над Памиром») — текст
 * не имел отношения к снимку. Подпись принадлежит материалу и должна
 * приходить из CMS, как и alt.
 *
 * Строка, а не переводимое поле: рядом лежит `cover_alt`, который тоже
 * обычная строка. Заводить здесь второй подход ради одного поля незачем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->string('cover_caption', 500)->nullable()->after('cover_alt');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->dropColumn('cover_caption');
        });
    }
};

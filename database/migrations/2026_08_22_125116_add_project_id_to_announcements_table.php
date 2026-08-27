<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Связь объявления с проектом.
 *
 * Тендеры публикуются как объявления, но принадлежат конкретному проекту.
 * Без этой связи страница проекта не может показать свои тендеры, а из
 * объявления некуда вернуться к проекту — публичная часть их не соединяла.
 *
 * `nullOnDelete`, а не каскад: удаление проекта не должно уносить объявление
 * о тендере — оно остаётся в архиве как самостоятельный документ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('org')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};

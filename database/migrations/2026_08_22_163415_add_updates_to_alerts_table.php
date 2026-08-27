<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * История обновлений предупреждения.
 *
 * Предупреждение живёт часами и меняется: зона расширилась, уровень снижен,
 * угроза снята. Читателю важно не только текущее состояние, но и то, что
 * изменилось и когда, — иначе он не понимает, свежая ли перед ним информация.
 *
 * Форма записи: `[{"at": "2026-08-22T10:00:00+05:00", "text": {"ru": "…"}}]`.
 * Метка времени одна на запись, а не по локалям, — иначе даты могли бы
 * разъехаться между языками у одного и того же события. Это отличается от
 * `Project::$goals`, где локаль стоит снаружи списка: там нет общих для всех
 * языков значений, здесь есть.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->json('updates')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropColumn('updates');
        });
    }
};

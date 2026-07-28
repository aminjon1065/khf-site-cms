<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->index(
                ['location', 'enabled', 'sort', 'parent_id'],
                'menu_items_public_tree_index',
            );
        });

        Schema::table('submissions', function (Blueprint $table): void {
            $table->index(
                ['status', 'created_at', 'id'],
                'submissions_status_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_public_tree_index');
        });

        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropIndex('submissions_status_created_index');
        });
    }
};

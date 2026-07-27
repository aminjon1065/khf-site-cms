<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D-6 (CMS_AUDIT.md P2): `parent_id` on `pages`/`menu_items` has been a
     * plain indexed column with no FK since the original migration — add
     * the constraint now that both tables are confirmed orphan-free.
     *
     * Different `onDelete` per table because the two trees have different
     * deletion models today:
     *
     * - `pages`: soft-deletes (`Page` uses `SoftDeletes`) and has no
     *   force-delete route yet, so this FK is currently inert in practice —
     *   a soft delete is an UPDATE, not a DELETE, and never fires it. It
     *   exists for when a force-delete path is eventually added. `nullOnDelete()`
     *   matches the sibling `author_id` FK already on this table (same
     *   original migration) and keeps a force-deleted page's children as
     *   valid top-level pages instead of cascading a whole subtree away.
     * - `menu_items`: hard-deletes only, no soft-delete trait. The only
     *   existing delete path (`MenuController::update()`) already refuses
     *   to delete an item that still has children (see its `$parentIds`
     *   exclusion). `restrictOnDelete()` turns that into a real DB
     *   guarantee instead of one controller's incidental behavior, so any
     *   future delete path (a single-item destroy route, tinker, a
     *   seeder) can't silently orphan live site-navigation children —
     *   it has to re-parent or remove them first.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('pages')->nullOnDelete();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('menu_items')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
    }
};

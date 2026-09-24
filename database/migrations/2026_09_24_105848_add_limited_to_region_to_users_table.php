<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Working only with one's own materials and the alerts of one's region used
 * to come with the «Региональный редактор» role. Roles are now built by the
 * administrator, so the limit becomes a setting of the account. Whoever held
 * that role keeps the limit — nobody's access widens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('limited_to_region')->default(false)->after('region_id');
        });

        $regionalRole = DB::table('roles')->where('name', 'regional_editor')->value('id');

        if ($regionalRole === null) {
            return;
        }

        DB::table('users')
            ->whereIn('id', DB::table('model_has_roles')
                ->select('model_id')
                ->where('role_id', $regionalRole)
                ->where('model_type', User::class))
            ->update(['limited_to_region' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('limited_to_region');
        });
    }
};

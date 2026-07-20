<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes projects to a full editorial content type: a public slug, lifecycle
 * status, funding metadata, structured goals/timeline/direction, a publish
 * timestamp, ordering and soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('lifecycle_status')->default('preparation')->index()->after('status');
            $table->string('code')->nullable()->after('lifecycle_status');
            $table->string('years')->nullable()->after('code');
            $table->string('customer')->nullable()->after('years');
            $table->string('partner')->nullable()->after('customer');
            $table->string('budget')->nullable()->after('partner');
            $table->json('goals')->nullable()->after('body');
            $table->json('timeline')->nullable()->after('goals');
            $table->json('direction')->nullable()->after('timeline');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->unsignedSmallInteger('sort')->default(0)->after('published_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug', 'lifecycle_status', 'code', 'years', 'customer', 'partner',
                'budget', 'goals', 'timeline', 'direction', 'published_at', 'sort', 'deleted_at',
            ]);
        });
    }
};

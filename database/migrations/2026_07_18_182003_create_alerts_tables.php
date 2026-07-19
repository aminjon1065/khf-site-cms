<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('internal_title');

            // translatable content
            $table->json('title')->nullable();
            $table->json('summary')->nullable();
            $table->json('body')->nullable();
            $table->json('instructions')->nullable();
            $table->json('contacts')->nullable();

            $table->string('hazard_type')->index();
            $table->string('severity')->index();
            $table->string('status')->default('draft')->index();

            $table->string('territory_type')->default('regions'); // country | regions
            $table->text('territory_note')->nullable();
            $table->string('risk_category')->nullable();
            $table->string('source')->nullable();

            $table->json('channels')->nullable();       // ["site","sos_app",...]

            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'severity']);
        });

        Schema::create('alert_region', function (Blueprint $table) {
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->primary(['alert_id', 'region_id']);
        });

        Schema::create('alert_district', function (Blueprint $table) {
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->primary(['alert_id', 'district_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_district');
        Schema::dropIfExists('alert_region');
        Schema::dropIfExists('alerts');
    }
};

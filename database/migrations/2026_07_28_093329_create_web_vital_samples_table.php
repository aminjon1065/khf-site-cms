<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_vital_samples', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 3);
            $table->decimal('value', 12, 4);
            $table->string('rating', 20);
            $table->string('route', 160);
            $table->string('locale', 2);
            $table->string('device', 10);
            $table->string('navigation_type', 32);
            $table->char('sample_hash', 64)->unique();
            $table->timestamps();

            $table->index(['metric', 'created_at'], 'web_vitals_metric_created_idx');
            $table->index(['route', 'metric', 'created_at'], 'web_vitals_route_metric_created_idx');
            $table->index(['device', 'metric', 'created_at'], 'web_vitals_device_metric_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_vital_samples');
    }
};

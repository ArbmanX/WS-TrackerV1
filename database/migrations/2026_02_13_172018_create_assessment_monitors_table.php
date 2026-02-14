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
        Schema::create('assessment_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38)->unique();
            $table->string('line_name', 255);
            $table->string('region', 50);
            $table->string('scope_year', 10);
            $table->string('cycle_type', 50)->nullable();
            $table->string('current_status', 10);
            $table->string('current_planner', 100)->nullable();
            $table->decimal('total_miles', 10, 4)->nullable();
            $table->jsonb('daily_snapshots')->default('{}');
            $table->jsonb('latest_snapshot')->nullable();
            $table->date('first_snapshot_date')->nullable();
            $table->date('last_snapshot_date')->nullable();
            $table->timestamps();

            $table->index('current_status');
            $table->index('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_monitors');
    }
};

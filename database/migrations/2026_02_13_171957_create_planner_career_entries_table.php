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
        Schema::create('planner_career_entries', function (Blueprint $table) {
            $table->id();
            $table->string('planner_username', 50);
            $table->string('planner_display_name', 100)->nullable();
            $table->string('job_guid', 38);
            $table->string('line_name', 255);
            $table->string('region', 50);
            $table->string('scope_year', 10);
            $table->string('cycle_type', 50)->nullable();
            $table->decimal('assessment_total_miles', 10, 4)->nullable();
            $table->decimal('assessment_completed_miles', 10, 4)->nullable();
            $table->date('assessment_pickup_date')->nullable();
            $table->date('assessment_qc_date')->nullable();
            $table->date('assessment_close_date')->nullable();
            $table->boolean('went_to_rework')->default(false);
            $table->jsonb('rework_details')->nullable();
            $table->jsonb('daily_metrics');
            $table->jsonb('summary_totals');
            $table->string('source', 20);
            $table->timestamps();

            $table->unique(['planner_username', 'job_guid']);
            $table->index('job_guid');
            $table->index('region');
            $table->index('scope_year');
            $table->index(['planner_username', 'scope_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planner_career_entries');
    }
};

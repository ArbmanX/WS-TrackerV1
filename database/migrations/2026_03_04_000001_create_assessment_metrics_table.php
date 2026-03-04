<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38)->unique();
            $table->string('work_order', 50);
            $table->string('extension', 10);

            // Permission breakdown
            $table->unsignedSmallInteger('total_units')->default(0);
            $table->unsignedSmallInteger('approved')->default(0);
            $table->unsignedSmallInteger('pending')->default(0);
            $table->unsignedSmallInteger('refused')->default(0);
            $table->unsignedSmallInteger('no_contact')->default(0);
            $table->unsignedSmallInteger('deferred')->default(0);
            $table->unsignedSmallInteger('ppl_approved')->default(0);

            // Notes compliance
            $table->unsignedSmallInteger('units_requiring_notes')->default(0);
            $table->unsignedSmallInteger('units_with_notes')->default(0);
            $table->unsignedSmallInteger('units_without_notes')->default(0);
            $table->decimal('notes_compliance_percent', 5, 1)->nullable();

            // Aging
            $table->unsignedSmallInteger('pending_over_threshold')->default(0);

            // Station breakdown
            $table->unsignedSmallInteger('stations_with_work')->default(0);
            $table->unsignedSmallInteger('stations_no_work')->default(0);
            $table->unsignedSmallInteger('stations_not_planned')->default(0);

            // Split assessment
            $table->unsignedSmallInteger('split_count')->nullable();
            $table->boolean('split_updated')->nullable();

            // Timeline dates
            $table->date('taken_date')->nullable();
            $table->date('sent_to_qc_date')->nullable();
            $table->date('sent_to_rework_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->date('first_unit_date')->nullable();
            $table->date('last_unit_date')->nullable();
            $table->date('oldest_pending_date')->nullable();

            // Oldest pending unit
            $table->string('oldest_pending_statname', 50)->nullable();
            $table->string('oldest_pending_unit', 20)->nullable();
            $table->unsignedInteger('oldest_pending_sequence')->nullable();

            // Work type breakdown (enriched JSON)
            $table->jsonb('work_type_breakdown')->default('[]');

            $table->timestamps();

            $table->foreign('job_guid')
                ->references('job_guid')
                ->on('assessments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_metrics');
    }
};

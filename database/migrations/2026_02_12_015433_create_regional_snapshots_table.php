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
        Schema::create('regional_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('scope_year', 4);
            $table->string('context_hash', 8);
            $table->string('region', 100);
            $table->string('contractor', 100)->nullable();

            // Core assessment metrics
            $table->integer('total_assessments')->default(0);
            $table->integer('active_count')->default(0);
            $table->integer('qc_count')->default(0);
            $table->integer('rework_count')->default(0);
            $table->integer('closed_count')->default(0);
            $table->decimal('total_miles', 10, 2)->default(0);
            $table->decimal('completed_miles', 10, 2)->default(0);
            $table->integer('active_planners')->default(0);

            // Permission counts
            $table->integer('total_units')->default(0);
            $table->integer('approved_count')->default(0);
            $table->integer('pending_count')->default(0);
            $table->integer('no_contact_count')->default(0);
            $table->integer('refusal_count')->default(0);
            $table->integer('deferred_count')->default(0);
            $table->integer('ppl_approved_count')->default(0);

            // Work measurements — counts
            $table->integer('rem_6_12_count')->default(0);
            $table->integer('rem_over_12_count')->default(0);
            $table->integer('ash_removal_count')->default(0);
            $table->integer('vps_count')->default(0);

            // Work measurements — areas/lengths
            $table->decimal('brush_acres', 10, 2)->default(0);
            $table->decimal('herbicide_acres', 10, 2)->default(0);
            $table->decimal('bucket_trim_length', 10, 2)->default(0);
            $table->decimal('manual_trim_length', 10, 2)->default(0);

            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['scope_year', 'captured_at']);
            $table->index(['region', 'captured_at']);
            $table->index('context_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_snapshots');
    }
};

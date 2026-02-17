<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38)->unique();
            $table->string('parent_job_guid', 38)->nullable();
            $table->foreignId('circuit_id')->constrained('circuits');
            $table->string('work_order', 50);
            $table->string('extension', 10);
            $table->string('job_type', 50);
            $table->string('status', 10);
            $table->string('scope_year', 4)->nullable();
            $table->boolean('is_split')->default(false);
            $table->boolean('taken')->default(false);
            $table->string('taken_by_username')->nullable();
            $table->string('modified_by_username')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('raw_title');
            $table->integer('version')->nullable();
            $table->integer('sync_version')->nullable();
            $table->string('cycle_type', 50)->nullable();
            $table->string('region', 20)->nullable();
            $table->integer('percent_complete')->nullable();
            $table->float('length')->nullable();
            $table->float('length_completed')->nullable();
            $table->timestamp('last_edited')->nullable();
            $table->float('last_edited_ole')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('last_synced_at');

            $table->index('parent_job_guid');
            $table->index('scope_year');
            $table->index('job_type');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreign('parent_job_guid')
                ->references('job_guid')
                ->on('assessments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};

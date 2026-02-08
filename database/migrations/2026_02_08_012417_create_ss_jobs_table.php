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
        Schema::create('ss_jobs', function (Blueprint $table) {
            $table->string('job_guid')->primary();
            $table->foreignId('circuit_id')->nullable()->constrained('circuits')->nullOnDelete();
            $table->string('parent_job_guid')->nullable();
            $table->foreignId('taken_by_id')->nullable()->constrained('ws_users')->nullOnDelete();
            $table->foreignId('modified_by_id')->nullable()->constrained('ws_users')->nullOnDelete();
            $table->string('work_order');
            $table->jsonb('extensions')->nullable();
            $table->string('job_type');
            $table->string('status');
            $table->string('scope_year');
            $table->timestamp('edit_date')->nullable();
            $table->boolean('taken')->default(false);
            $table->string('version')->nullable();
            $table->string('sync_version')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('raw_title');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('parent_job_guid');
            $table->index('scope_year');
            $table->index('status');
            $table->index('job_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ss_jobs');
    }
};

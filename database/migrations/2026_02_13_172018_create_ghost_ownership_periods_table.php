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
        Schema::create('ghost_ownership_periods', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38);
            $table->string('line_name', 255);
            $table->string('region', 50);
            $table->date('takeover_date');
            $table->string('takeover_username', 150);
            $table->date('return_date')->nullable();
            $table->integer('baseline_unit_count');
            $table->jsonb('baseline_snapshot');
            $table->boolean('is_parent_takeover')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('job_guid');
            $table->index('status');
            $table->index(['job_guid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghost_ownership_periods');
    }
};

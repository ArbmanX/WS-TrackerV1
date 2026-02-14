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
        Schema::create('ghost_unit_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_period_id')
                ->nullable()
                ->constrained('ghost_ownership_periods')
                ->nullOnDelete();
            $table->string('job_guid', 38);
            $table->string('line_name', 255);
            $table->string('region', 50);
            $table->string('unitguid', 38);
            $table->string('unit_type', 20);
            $table->string('statname', 100);
            $table->string('permstat_at_snapshot', 30)->nullable();
            $table->string('forester', 100)->nullable();
            $table->date('detected_date');
            $table->date('takeover_date');
            $table->string('takeover_username', 150);
            $table->timestamp('created_at')->nullable();

            $table->index('job_guid');
            $table->index('ownership_period_id');
            $table->index('detected_date');
            $table->index('unitguid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghost_unit_evidence');
    }
};

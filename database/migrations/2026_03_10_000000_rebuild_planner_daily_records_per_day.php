<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure planner_daily_records from per-unit rows to per-day rows.
     * Data must be re-synced from the API after this migration.
     */
    public function up(): void
    {
        Schema::dropIfExists('planner_daily_records');

        Schema::create('planner_daily_records', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38)->index();
            $table->string('frstr_user', 50)->index();
            $table->string('work_order', 20);
            $table->string('extension', 10)->default('@');
            $table->date('assess_date');
            $table->decimal('span_length_ft', 12, 4)->default(0);
            $table->decimal('span_miles', 12, 9)->default(0);
            $table->unsignedInteger('station_count')->default(0);
            $table->jsonb('stations')->default('[]');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['work_order', 'extension', 'frstr_user', 'assess_date'],
                'planner_daily_records_day_unique'
            );

            $table->index('assess_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_daily_records');
    }
};

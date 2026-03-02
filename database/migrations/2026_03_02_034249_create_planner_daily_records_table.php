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
        Schema::create('planner_daily_records', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38)->index();
            $table->string('frstr_user', 50)->index();
            $table->string('work_order', 20);
            $table->string('extension', 10)->default('@');
            $table->date('assess_date')->nullable();
            $table->string('stat_name', 20);
            $table->integer('sequence')->default(0);
            $table->string('unit_guid', 38)->nullable();
            $table->string('unit', 10)->nullable();
            $table->decimal('lat', 15, 12)->nullable();
            $table->decimal('long', 15, 12)->nullable();
            $table->string('coord_source', 10)->nullable();
            $table->decimal('span_length', 12, 6)->nullable();
            $table->decimal('span_miles', 12, 9)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['work_order', 'extension', 'stat_name', 'sequence'],
                'planner_daily_records_composite_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planner_daily_records');
    }
};

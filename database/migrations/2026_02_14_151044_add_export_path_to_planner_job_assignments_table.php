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
        Schema::table('planner_job_assignments', function (Blueprint $table) {
            $table->string('export_path', 255)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('planner_job_assignments', function (Blueprint $table) {
            $table->dropColumn('export_path');
        });
    }
};

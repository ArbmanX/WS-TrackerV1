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
        Schema::create('planner_job_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('frstr_user', 50)->index();
            $table->string('job_guid', 38)->index();
            $table->string('status', 20)->default('discovered');
            $table->timestamp('discovered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['frstr_user', 'job_guid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planner_job_assignments');
    }
};

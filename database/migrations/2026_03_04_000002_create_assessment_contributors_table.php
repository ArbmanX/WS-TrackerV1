<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_contributors', function (Blueprint $table) {
            $table->id();
            $table->string('job_guid', 38);
            $table->string('ws_username', 100);
            $table->foreignId('ws_user_id')->nullable()->constrained('ws_users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('unit_count')->default(0);
            $table->string('role', 50)->nullable();
            $table->timestamps();

            $table->unique(['job_guid', 'ws_username']);
            $table->index('job_guid');

            $table->foreign('job_guid')
                ->references('job_guid')
                ->on('assessments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_contributors');
    }
};

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
        Schema::create('system_wide_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('scope_year', 4);
            $table->string('context_hash', 8);
            $table->string('contractor', 100)->nullable();
            $table->integer('total_assessments')->default(0);
            $table->integer('active_count')->default(0);
            $table->integer('qc_count')->default(0);
            $table->integer('rework_count')->default(0);
            $table->integer('closed_count')->default(0);
            $table->decimal('total_miles', 10, 2)->default(0);
            $table->decimal('completed_miles', 10, 2)->default(0);
            $table->integer('active_planners')->default(0);
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['scope_year', 'captured_at']);
            $table->index('context_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_wide_snapshots');
    }
};

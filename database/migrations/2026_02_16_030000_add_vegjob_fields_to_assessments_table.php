<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('planned_emergent', 50)->nullable()->after('region');
            $table->float('voltage')->nullable()->after('planned_emergent');
            $table->string('cost_method', 10)->nullable()->after('voltage');
            $table->string('program_name', 200)->nullable()->after('cost_method');
            $table->boolean('permissioning_required')->nullable()->after('program_name');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn([
                'planned_emergent',
                'voltage',
                'cost_method',
                'program_name',
                'permissioning_required',
            ]);
        });
    }
};

<?php

use App\Models\PlannerJobAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_job_assignments', function (Blueprint $table) {
            $table->string('normalized_username')->nullable()->after('frstr_user')->index();
        });

        // Backfill in PHP — avoids fragile SQL backslash escaping through PHP->PDO->PostgreSQL layers
        PlannerJobAssignment::whereNull('normalized_username')->each(function (PlannerJobAssignment $record) {
            $username = $record->frstr_user;
            $record->update([
                'normalized_username' => str_contains($username, '\\')
                    ? substr($username, strrpos($username, '\\') + 1)
                    : $username,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('planner_job_assignments', function (Blueprint $table) {
            $table->dropColumn('normalized_username');
        });
    }
};

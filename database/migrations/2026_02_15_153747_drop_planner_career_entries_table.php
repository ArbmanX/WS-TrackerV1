<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('planner_career_entries');
    }

    public function down(): void
    {
        // Table recreation not supported — re-run the original create migration if needed.
    }
};

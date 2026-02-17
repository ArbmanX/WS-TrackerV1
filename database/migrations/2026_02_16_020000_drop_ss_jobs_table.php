<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ss_jobs');
    }

    public function down(): void
    {
        // ss_jobs replaced by assessments — no rollback
    }
};

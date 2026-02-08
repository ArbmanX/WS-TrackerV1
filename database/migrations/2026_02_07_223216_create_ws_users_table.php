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
        Schema::create('ws_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('domain');
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_enabled')->nullable();
            $table->jsonb('groups')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ws_users');
    }
};

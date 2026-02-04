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
        Schema::table('users', function (Blueprint $table) {
            $table->string('ws_username')->nullable()->after('email');
            $table->string('ws_full_name')->nullable()->after('ws_username');
            $table->string('ws_domain', 100)->nullable()->after('ws_full_name');
            $table->json('ws_groups')->nullable()->after('ws_domain');
            $table->timestamp('ws_validated_at')->nullable()->after('ws_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ws_username',
                'ws_full_name',
                'ws_domain',
                'ws_groups',
                'ws_validated_at',
            ]);
        });
    }
};

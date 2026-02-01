<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores encrypted WorkStudio credentials for API access.
     * Some users may need their own credentials for impersonated requests.
     */
    public function up(): void
    {
        Schema::create('user_ws_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('encrypted_username')->comment('Encrypted WS username');
            $table->text('encrypted_password')->comment('Encrypted WS password');
            $table->boolean('is_valid')->default(true)->comment('Last validation result');
            $table->timestamp('validated_at')->nullable()->comment('Last successful validation');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ws_credentials');
    }
};

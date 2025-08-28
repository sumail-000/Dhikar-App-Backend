<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['email', 'code']);
        });

        // Clean up existing password_resets table if exists (optional)
        if (Schema::hasTable('password_resets')) {
            DB::statement('DELETE FROM password_resets');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};

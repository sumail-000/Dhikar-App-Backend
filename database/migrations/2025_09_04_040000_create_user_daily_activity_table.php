<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('activity_date');
            $table->boolean('opened')->default(false);
            $table->boolean('reading')->default(false); // client-only personal dhikr or any generic reading ping
            $table->timestamps();

            $table->unique(['user_id', 'activity_date']);
            $table->index(['user_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_activity');
    }
};

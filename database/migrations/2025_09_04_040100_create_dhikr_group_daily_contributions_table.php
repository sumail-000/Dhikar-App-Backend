<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhikr_group_daily_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dhikr_group_id')->constrained('dhikr_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('contribution_date');
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['dhikr_group_id', 'user_id', 'contribution_date'], 'dhikr_group_daily_unique');
            $table->index(['user_id', 'contribution_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhikr_group_daily_contributions');
    }
};

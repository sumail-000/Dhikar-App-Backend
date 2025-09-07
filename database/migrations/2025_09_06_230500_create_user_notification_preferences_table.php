<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('allow_group_notifications')->default(true);
            $table->boolean('allow_motivational_notifications')->default(true);
            $table->boolean('allow_personal_reminders')->default(true);
            $table->string('preferred_personal_reminder_hour', 2)->nullable(); // e.g. '18'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};


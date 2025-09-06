<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('device_token', 1024)->nullable()->index();
            $table->string('notification_type')->nullable(); // e.g., group_khitma_reminder, dhikr_group_reminder
            $table->string('event'); // queued, sent_v1, sent_legacy, error, received, opened
            $table->json('payload')->nullable(); // arbitrary payload sent/received
            $table->json('provider_response')->nullable(); // response from FCM if available
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'event']);
            $table->index(['notification_type', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_events');
    }
};

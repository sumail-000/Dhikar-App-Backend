<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhikr_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('days_to_complete')->nullable();
            $table->unsignedInteger('members_target')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('dhikr_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dhikr_group_id')->constrained('dhikr_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['dhikr_group_id', 'user_id']);
            $table->index(['dhikr_group_id', 'user_id']);
        });

        Schema::create('dhikr_invite_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dhikr_group_id')->constrained('dhikr_groups')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('dhikr_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhikr_invite_tokens');
        Schema::dropIfExists('dhikr_group_members');
        Schema::dropIfExists('dhikr_groups');
    }
};


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
        // groups
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['khitma', 'dhikr'])->default('khitma');
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            // Khitma-specific optional settings
            $table->unsignedTinyInteger('days_to_complete')->nullable(); // e.g., 3/4/5/6
            $table->date('start_date')->nullable();
            $table->timestamps();
        });

        // group_members
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        // invite_tokens (join via token)
        Schema::create('invite_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('group_id');
        });

        // khitma_assignments (Juz 1..30 per group)
        Schema::create('khitma_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('juz_number'); // 1..30
            $table->enum('status', ['unassigned', 'assigned', 'completed'])->default('unassigned');
            $table->unsignedInteger('pages_read')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'juz_number']);
            $table->index(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khitma_assignments');
        Schema::dropIfExists('invite_tokens');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};

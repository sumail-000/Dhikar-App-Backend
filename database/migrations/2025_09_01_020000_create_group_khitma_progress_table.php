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
        // group_khitma_progress - tracks individual member's reading progress in group khitma
        Schema::create('group_khitma_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('reading_date'); // Date of this reading session
            
            // Reading session details
            $table->unsignedTinyInteger('juzz_read'); // Which Juzz was read
            $table->unsignedInteger('surah_read'); // Which Surah was read
            $table->unsignedInteger('page_read'); // Page read in this session
            $table->unsignedInteger('start_verse')->nullable(); // Starting verse
            $table->unsignedInteger('end_verse')->nullable(); // Ending verse
            
            // Session metadata
            $table->text('notes')->nullable(); // Optional user notes
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['group_id', 'user_id', 'reading_date']);
            $table->index(['group_id', 'juzz_read']);
            $table->index(['user_id', 'reading_date']);
        });

        // Update groups table to add more khitma tracking fields
        Schema::table('groups', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('groups', 'members_target')) {
                $table->unsignedTinyInteger('members_target')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('groups', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('members_target');
            }
            if (!Schema::hasColumn('groups', 'auto_assign_enabled')) {
                $table->boolean('auto_assign_enabled')->default(false)->after('is_public');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['members_target', 'is_public', 'auto_assign_enabled']);
        });
        
        Schema::dropIfExists('group_khitma_progress');
    }
};

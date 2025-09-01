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
        // personal_khitma_progress - tracks individual user's personal Quran completion progress
        Schema::create('personal_khitma_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('khitma_name'); // User-defined name for their khitma
            $table->unsignedInteger('total_days'); // Total days planned for completion
            $table->date('start_date'); // When the khitma was started
            $table->date('target_completion_date'); // Calculated completion date
            
            // Current reading position
            $table->unsignedTinyInteger('current_juzz')->default(1); // Current Juzz (1-30)
            $table->unsignedInteger('current_surah')->default(1); // Current Surah (1-114)
            $table->unsignedInteger('current_page')->default(1); // Current page (1-604)
            $table->unsignedInteger('current_verse')->default(1); // Current verse within surah
            
            // Progress tracking
            $table->unsignedInteger('total_pages_read')->default(0); // Total pages read (out of 604)
            $table->unsignedTinyInteger('juzz_completed')->default(0); // Number of Juzz completed (0-30)
            $table->decimal('completion_percentage', 5, 2)->default(0.00); // Percentage completed (0.00-100.00)
            
            // Status and timestamps
            $table->enum('status', ['active', 'completed', 'paused'])->default('active');
            $table->timestamp('last_read_at')->nullable(); // Last time user read
            $table->timestamp('completed_at')->nullable(); // When khitma was completed
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'start_date']);
        });

        // personal_khitma_daily_progress - detailed daily reading logs
        Schema::create('personal_khitma_daily_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('khitma_id')->constrained('personal_khitma_progress')->cascadeOnDelete();
            $table->date('reading_date'); // Date of this reading session
            
            // Reading session details
            $table->unsignedTinyInteger('juzz_read'); // Which Juzz was read
            $table->unsignedInteger('surah_read'); // Which Surah was read
            $table->unsignedInteger('start_page'); // Starting page of session
            $table->unsignedInteger('end_page'); // Ending page of session
            $table->unsignedInteger('pages_read'); // Number of pages read in this session
            $table->unsignedInteger('start_verse')->nullable(); // Starting verse
            $table->unsignedInteger('end_verse')->nullable(); // Ending verse
            
            // Session metadata
            $table->unsignedInteger('reading_duration_minutes')->nullable(); // How long they read
            $table->text('notes')->nullable(); // Optional user notes
            $table->timestamps();
            
            // Indexes
            $table->index(['khitma_id', 'reading_date']);
            $table->index(['khitma_id', 'juzz_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_khitma_daily_progress');
        Schema::dropIfExists('personal_khitma_progress');
    }
};

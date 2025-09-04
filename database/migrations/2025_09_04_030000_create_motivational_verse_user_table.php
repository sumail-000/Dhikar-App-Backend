<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivational_verse_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('verse_id')->constrained('motivational_verses')->cascadeOnDelete();
            $table->date('shown_date');
            $table->timestamps();

            $table->index(['user_id', 'shown_date']);
            $table->unique(['user_id', 'shown_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivational_verse_user');
    }
};

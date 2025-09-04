<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_dhikrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('title_arabic')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('subtitle_arabic')->nullable();
            $table->text('arabic_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_dhikrs');
    }
};


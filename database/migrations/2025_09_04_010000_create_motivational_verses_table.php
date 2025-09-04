<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivational_verses', function (Blueprint $table) {
            $table->id();
            $table->string('surah_name')->nullable();
            $table->string('surah_name_ar')->nullable();
            $table->unsignedInteger('surah_number')->nullable();
            $table->unsignedInteger('ayah_number')->nullable();
            $table->text('arabic_text')->nullable();
            $table->text('translation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivational_verses');
    }
};

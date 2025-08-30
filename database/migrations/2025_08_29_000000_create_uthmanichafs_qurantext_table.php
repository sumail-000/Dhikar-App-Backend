<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UthmanicHafs_QuranText', function (Blueprint $table) {
            // Match columns in the SQL dump
            $table->bigInteger('id')->primary();
            $table->unsignedTinyInteger('jozz'); // juz (1..30)
            $table->unsignedSmallInteger('page'); // page number
            $table->unsignedTinyInteger('sura_no');
            $table->string('sura_name_en', 64);
            $table->string('sura_name_ar', 64);
            $table->unsignedTinyInteger('line_start');
            $table->unsignedTinyInteger('line_end');
            $table->unsignedSmallInteger('aya_no');
            $table->text('aya_text');
            $table->text('aya_text_emlaey');

            // Helpful indexes for lookups
            $table->index(['sura_no', 'aya_no']);
            $table->index(['jozz']);
            $table->index(['page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UthmanicHafs_QuranText');
    }
};
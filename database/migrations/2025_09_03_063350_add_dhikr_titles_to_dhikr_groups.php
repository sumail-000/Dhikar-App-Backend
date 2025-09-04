<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dhikr_groups', function (Blueprint $table) {
            $table->string('dhikr_title', 255)->nullable()->after('name');
            $table->string('dhikr_title_arabic', 255)->nullable()->after('dhikr_title');
        });
    }

    public function down(): void
    {
        Schema::table('dhikr_groups', function (Blueprint $table) {
            if (Schema::hasColumn('dhikr_groups', 'dhikr_title_arabic')) {
                $table->dropColumn('dhikr_title_arabic');
            }
            if (Schema::hasColumn('dhikr_groups', 'dhikr_title')) {
                $table->dropColumn('dhikr_title');
            }
        });
    }
};


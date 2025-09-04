<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dhikr_group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('dhikr_group_members', 'dhikr_contribution')) {
                $table->unsignedBigInteger('dhikr_contribution')->default(0)->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dhikr_group_members', function (Blueprint $table) {
            if (Schema::hasColumn('dhikr_group_members', 'dhikr_contribution')) {
                $table->dropColumn('dhikr_contribution');
            }
        });
    }
};

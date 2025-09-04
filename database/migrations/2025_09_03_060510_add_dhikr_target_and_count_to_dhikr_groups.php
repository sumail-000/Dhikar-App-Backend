<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dhikr_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('dhikr_count')->default(0)->after('members_target');
            $table->unsignedInteger('dhikr_target')->nullable()->after('dhikr_count');
        });
    }

    public function down(): void
    {
        Schema::table('dhikr_groups', function (Blueprint $table) {
            if (Schema::hasColumn('dhikr_groups', 'dhikr_target')) {
                $table->dropColumn('dhikr_target');
            }
            if (Schema::hasColumn('dhikr_groups', 'dhikr_count')) {
                $table->dropColumn('dhikr_count');
            }
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'members_target')) {
                $table->unsignedInteger('members_target')->nullable()->after('creator_id');
            }
            if (!Schema::hasColumn('groups', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('members_target');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'is_public')) {
                $table->dropColumn('is_public');
            }
            if (Schema::hasColumn('groups', 'members_target')) {
                $table->dropColumn('members_target');
            }
        });
    }
};


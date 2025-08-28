<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 2 backfill: ensure username is populated and mirror name = username
        if (Schema::hasColumn('users', 'name')) {
            // 1) If username is null/empty, copy from name
            DB::statement("UPDATE users SET username = name WHERE (username IS NULL OR username = '') AND name IS NOT NULL AND name <> ''");
            // 2) Mirror: set name to username for all rows (compat layer while we still expose 'name')
            DB::statement("UPDATE users SET name = username WHERE username IS NOT NULL");
        }
    }

    public function down(): void
    {
        // No-op: previous 'name' values cannot be restored reliably
    }
};


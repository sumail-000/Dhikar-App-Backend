<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class UthmanicHafsQuranTextSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'UthmanicHafs_QuranText';
        $sqlPath = database_path('seeders/sql/hafsData_v2-0.sql');
        if (!File::exists($sqlPath)) {
            $this->command?->error("SQL file not found: {$sqlPath}");
            return;
        }

        // Ensure utf8mb4 for Quran text
        try {
            DB::statement("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
        } catch (\Throwable $e) {
            // ignore if driver handles it
        }

        // Stream file and execute statement-by-statement to avoid max_allowed_packet issues
        $handle = fopen($sqlPath, 'r');
        if (!$handle) {
            $this->command?->error('Unable to open SQL file for reading.');
            return;
        }

        $inBlockComment = false;
        $buffer = '';
        $count = 0;

        // Optional: truncate table before import
        DB::table($table)->truncate();

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) break;

            // Handle block comments /* ... */ possibly spanning lines
            if ($inBlockComment) {
                if (strpos($line, '*/') !== false) {
                    $inBlockComment = false;
                }
                continue;
            }
            if (preg_match('/^\s*\/\*/', $line)) {
                if (strpos($line, '*/') === false) {
                    $inBlockComment = true;
                }
                continue;
            }
            // Skip single-line comments starting with --
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }

            // Replace empty table name with our table
            $line = preg_replace('/INSERT INTO\s+``/i', "INSERT INTO `{$table}`", $line);

            $buffer .= $line;

            // If line ends a statement
            if (str_ends_with(trim($line), ';')) {
                $stmt = trim($buffer);
                $buffer = '';

                if ($stmt !== '') {
                    DB::unprepared($stmt);
                    $count++;
                    if ($count % 500 === 0) {
                        $this->command?->info("Imported {$count} rows...");
                    }
                }
            }
        }
        fclose($handle);

        $this->command?->info("UthmanicHafs_QuranText seeded successfully. Total rows: {$count}");
    }
}
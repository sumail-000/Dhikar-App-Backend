<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MotivationalVersesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/motivational_verses.json');
        if (!File::exists($path)) {
            return;
        }
        $json = File::get($path);
        $data = json_decode($json, true) ?? [];
        $now = now();
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
'surah_name' => $item['surah_name'] ?? null,
                'surah_name_ar' => $item['surah_name_ar'] ?? null,
                'surah_number' => $item['surah_number'] ?? null,
                'ayah_number' => $item['ayah_number'] ?? null,
                'arabic_text' => $item['arabic_text'] ?? null,
                'translation' => $item['translation'] ?? null,
                'is_active' => $item['is_active'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($rows)) {
            DB::table('motivational_verses')->insert($rows);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MotivationalVerse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminMotivationalVerseController extends Controller
{
    // GET /api/admin/motivational-verses
    public function index()
    {
        $verses = MotivationalVerse::orderByDesc('id')->get();
        return response()->json([
            'ok' => true,
            'data' => $verses,
        ]);
    }

    // GET /api/admin/motivational-verses/{id}
    public function show(int $id)
    {
        $verse = MotivationalVerse::findOrFail($id);
        return response()->json([
            'ok' => true,
            'data' => $verse,
        ]);
    }

    // POST /api/admin/motivational-verses
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'surah_name' => 'nullable|string|max:255',
            'surah_name_ar' => 'nullable|string|max:255',
            'surah_number' => 'nullable|integer|min:1|max:114',
            'ayah_number' => 'nullable|integer|min:1',
            'arabic_text' => 'required|string',
            'translation' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $v->validate();

        $data = $v->validated();
        $verse = MotivationalVerse::create([
            'surah_name' => $data['surah_name'] ?? null,
            'surah_name_ar' => $data['surah_name_ar'] ?? null,
            'surah_number' => $data['surah_number'] ?? null,
            'ayah_number' => $data['ayah_number'] ?? null,
            'arabic_text' => $data['arabic_text'],
            'translation' => $data['translation'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $verse,
        ], 201);
    }

    // PUT /api/admin/motivational-verses/{id}
    public function update(Request $request, int $id)
    {
        $verse = MotivationalVerse::findOrFail($id);
        $v = Validator::make($request->all(), [
            'surah_name' => 'nullable|string|max:255',
            'surah_name_ar' => 'nullable|string|max:255',
            'surah_number' => 'nullable|integer|min:1|max:114',
            'ayah_number' => 'nullable|integer|min:1',
            'arabic_text' => 'required|string',
            'translation' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $v->validate();

        $data = $v->validated();
        $verse->update([
            'surah_name' => $data['surah_name'] ?? null,
            'surah_name_ar' => $data['surah_name_ar'] ?? null,
            'surah_number' => $data['surah_number'] ?? null,
            'ayah_number' => $data['ayah_number'] ?? null,
            'arabic_text' => $data['arabic_text'],
            'translation' => $data['translation'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $verse->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $verse->refresh(),
        ]);
    }

    // DELETE /api/admin/motivational-verses/{id}
    public function destroy(int $id)
    {
        $verse = MotivationalVerse::findOrFail($id);
        $verse->delete();
        return response()->json([
            'ok' => true,
            'message' => 'Verse deleted',
        ]);
    }

    // POST /api/admin/motivational-verses/{id}/toggle
    public function toggle(int $id)
    {
        $verse = MotivationalVerse::findOrFail($id);
        $verse->is_active = !$verse->is_active;
        $verse->save();
        return response()->json([
            'ok' => true,
            'data' => $verse,
        ]);
    }
}


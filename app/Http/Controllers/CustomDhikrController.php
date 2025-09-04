<?php

namespace App\Http\Controllers;

use App\Models\CustomDhikr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomDhikrController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = CustomDhikr::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();
        return response()->json(['custom_dhikr' => $items]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'subtitle_arabic' => ['nullable', 'string', 'max:255'],
            'arabic_text' => ['required', 'string', 'max:2000'],
        ]);

        if ((empty($data['title']) && empty($data['title_arabic']))) {
            return response()->json(['message' => 'Either title or title_arabic is required'], 422);
        }

        $item = CustomDhikr::create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? $data['title_arabic'],
            'title_arabic' => $data['title_arabic'] ?? $data['title'],
            'subtitle' => $data['subtitle'] ?? '',
            'subtitle_arabic' => $data['subtitle_arabic'] ?? $data['subtitle'] ?? '',
            'arabic_text' => $data['arabic_text'],
        ]);

        return response()->json(['custom_dhikr' => $item], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $item = CustomDhikr::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'subtitle_arabic' => ['nullable', 'string', 'max:255'],
            'arabic_text' => ['nullable', 'string', 'max:2000'],
        ]);

        // Keep symmetry between english/ar titles if only one provided
        if (array_key_exists('title', $data) && empty($data['title']) && !empty($data['title_arabic'])) {
            $data['title'] = $data['title_arabic'];
        }
        if (array_key_exists('title_arabic', $data) && empty($data['title_arabic']) && !empty($data['title'])) {
            $data['title_arabic'] = $data['title'];
        }
        if (array_key_exists('subtitle_arabic', $data) && empty($data['subtitle_arabic']) && !empty($data['subtitle'])) {
            $data['subtitle_arabic'] = $data['subtitle'];
        }

        $item->fill($data);
        $item->save();

        return response()->json(['custom_dhikr' => $item]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $item = CustomDhikr::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $item->delete();
        return response()->json(['deleted' => true]);
    }
}


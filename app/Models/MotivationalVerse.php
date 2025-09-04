<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotivationalVerse extends Model
{
    use HasFactory;

    protected $fillable = [
'surah_name',
        'surah_name_ar',
        'surah_number',
        'ayah_number',
        'arabic_text',
        'translation',
        'is_active',
    ];
}

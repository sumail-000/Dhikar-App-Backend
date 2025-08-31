<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UthmanicHafsQuranText extends Model
{
    protected $table = 'UthmanicHafs_QuranText';
    protected $primaryKey = 'id';
    public $incrementing = false; // id is provided by dump
    public $timestamps = false;

    protected $fillable = [
        'id','jozz','page','sura_no','sura_name_en','sura_name_ar','line_start','line_end','aya_no','aya_text','aya_text_emlaey'
    ];

    // Expose a virtual `juz` attribute for API consistency
    protected $appends = ['juz'];

    public function getJuzAttribute(): ?int
    {
        return isset($this->attributes['jozz']) ? (int) $this->attributes['jozz'] : null;
    }
}

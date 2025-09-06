<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_token',
        'notification_type',
        'event',
        'payload',
        'provider_response',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'provider_response' => 'array',
    ];
}

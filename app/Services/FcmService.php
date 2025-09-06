<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\PushEvent;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;

class FcmService
{
    protected ?string $serverKey = null;
    protected bool $useV1 = false;
    protected ?FcmV1Service $v1 = null;

    public function __construct()
    {
        $this->serverKey = (string) config('services.fcm.server_key', env('FCM_SERVER_KEY')) ?: null;
        $this->useV1 = (bool) env('FCM_USE_V1', false);

        if ($this->useV1) {
            $this->v1 = new FcmV1Service();
        }
    }

    /**
     * Send a push notification to multiple tokens.
     * If FCM_USE_V1=true, use HTTP v1 with service account; otherwise legacy key.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) {
            return;
        }

        Log::info('FCM: preparing to send (legacy/v1 switch)', [
            'use_v1' => $this->useV1,
            'tokens_count' => count($tokens),
            'notification_type' => $data['type'] ?? null,
            'title' => $title,
        ]);

        if ($this->useV1 && $this->v1) {
            // Use HTTP v1 API (chunks handled by service)
            $this->v1->sendToTokens($tokens, $title, $body, $data);
            return;
        }

        if (empty($this->serverKey)) {
            // No legacy key and v1 disabled: nothing to do
            return;
        }

        // Legacy HTTP API fallback
        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            Log::info('FCM (legacy): sending chunk', [
                'size' => count($chunk),
                'notification_type' => $data['type'] ?? null,
            ]);
            $payload = [
                'registration_ids' => array_values($chunk),
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
                'priority' => 'high',
            ];

            try {
                // Log push events per token (legacy)
                foreach ($chunk as $t) {
                    $dt = DeviceToken::where('device_token', $t)->first();
                    PushEvent::create([
                        'user_id' => $dt?->user_id,
                        'device_token' => $t,
                        'notification_type' => $data['type'] ?? null,
                        'event' => 'sent_legacy',
                        'payload' => [
                            'title' => $title,
                            'body' => $body,
                            'data' => $data,
                        ],
                    ]);
                }
                Http::withHeaders([
                    'Authorization' => 'key='.$this->serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', $payload);
            } catch (\Throwable $e) {
                Log::error('FCM (legacy) send error', [
                    'message' => $e->getMessage(),
                ]);
                foreach ($chunk as $t) {
                    $dt = DeviceToken::where('device_token', $t)->first();
                    PushEvent::create([
                        'user_id' => $dt?->user_id,
                        'device_token' => $t,
                        'notification_type' => $data['type'] ?? null,
                        'event' => 'error',
                        'error_message' => $e->getMessage(),
                    ]);
                }
                // swallow errors; consider logging in production
            }
        }
    }
}

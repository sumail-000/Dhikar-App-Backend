<?php

namespace App\Services;

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
                Http::withHeaders([
                    'Authorization' => 'key='.$this->serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', $payload);
            } catch (\Throwable $e) {
                // swallow errors; consider logging in production
            }
        }
    }
}

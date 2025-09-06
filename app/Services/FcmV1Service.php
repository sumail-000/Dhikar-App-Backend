<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\PushEvent;
use Illuminate\Support\Facades\Log;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;

class FcmV1Service
{
    protected string $projectId;
    protected string $credentialsPath;

    public function __construct()
    {
        // Default to the service account file you provided
        $this->credentialsPath = (string) env('FCM_SERVICE_ACCOUNT', storage_path('app/dhikr-app-f8c40-a0045c8be10b.json'));

        // Derive project_id from the JSON file
        $creds = json_decode(@file_get_contents($this->credentialsPath), true) ?: [];
        $this->projectId = (string) ($creds['project_id'] ?? env('FIREBASE_PROJECT_ID', ''));
    }

    /**
     * Send notifications using FCM HTTP v1 API.
     * Chunks tokens to ~500 per request to be safe.
     *
     * @param array<int,string> $tokens
     * @param array<string,mixed> $data
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens) || empty($this->projectId) || ! is_file($this->credentialsPath)) {
            return;
        }

        $endpoint = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $this->projectId);

        Log::info('FCM (v1): sending tokens', [
            'count' => count($tokens),
            'notification_type' => $data['type'] ?? null,
            'title' => $title,
        ]);

        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $token) {
                Log::info('FCM (v1): sending to token');
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $this->convertDataForFcm($data),
                    ],
                ];

                try {
                    // Log push event per token (v1) before send
                    $dt = DeviceToken::where('device_token', $token)->first();
                    PushEvent::create([
                        'user_id' => $dt?->user_id,
                        'device_token' => $token,
                        'notification_type' => $data['type'] ?? null,
                        'event' => 'sent_v1',
                        'payload' => [
                            'title' => $title,
                            'body' => $body,
                            'data' => $data,
                        ],
                    ]);
                    $accessToken = $this->getAccessToken();
                    Http::withToken($accessToken)
                        ->acceptJson()
                        ->post($endpoint, $payload)
                        ->throw();
                } catch (\Throwable $e) {
                    Log::error('FCM (v1) send error', [
                        'message' => $e->getMessage(),
                    ]);
                    $dt = DeviceToken::where('device_token', $token)->first();
                    PushEvent::create([
                        'user_id' => $dt?->user_id,
                        'device_token' => $token,
                        'notification_type' => $data['type'] ?? null,
                        'event' => 'error',
                        'error_message' => $e->getMessage(),
                    ]);
                    // swallow errors; consider logging in production
                }
            }
        }
    }

    /**
     * Convert data array to FCM-compatible format (flat string values only)
     */
    protected function convertDataForFcm(array $data): array
    {
        $converted = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Convert arrays to JSON strings for FCM
                $converted[$key] = json_encode($value);
            } else {
                // Convert all other values to strings
                $converted[$key] = (string) $value;
            }
        }
        return $converted;
    }

    protected function getAccessToken(): string
    {
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountCredentials($scopes, $this->credentialsPath);
        $token = $credentials->fetchAuthToken();
        return (string) ($token['access_token'] ?? '');
    }
}


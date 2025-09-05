<?php

namespace App\Services;

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

        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $token) {
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ];

                try {
                    $accessToken = $this->getAccessToken();
                    Http::withToken($accessToken)
                        ->acceptJson()
                        ->post($endpoint, $payload)
                        ->throw();
                } catch (\Throwable $e) {
                    // swallow errors; consider logging in production
                }
            }
        }
    }

    protected function getAccessToken(): string
    {
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountCredentials($scopes, $this->credentialsPath);
        $token = $credentials->fetchAuthToken();
        return (string) ($token['access_token'] ?? '');
    }
}


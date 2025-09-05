<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, string> */
    public array $tokens;
    public string $title;
    public string $body;
    /** @var array<string,mixed> */
    public array $data;

    /**
     * @param array<int,string> $tokens
     * @param array<string,mixed> $data
     */
    public function __construct(array $tokens, string $title, string $body, array $data = [])
    {
        $this->tokens = $tokens;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    public function handle(FcmService $fcm): void
    {
        $fcm->sendToTokens($this->tokens, $this->title, $this->body, $this->data);
    }
}


<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsappJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $phone,
        public string $message,
    ) {}

    public function handle(): void
    {
        try {
            $response = Http::post('http://localhost:8080/api/sendtext', [
                'sessions' => 'session_1',
                'target' => $this->phone,
                'message' => $this->message,
                // 'url' => $this->url,
            ]);

            if ($response->failed()) {
                Log::error('WA Gateway gagal', $response->json());
            }
            Log::info($response->json());
        } catch (\Throwable $e) {
            Log::error('WA Exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}

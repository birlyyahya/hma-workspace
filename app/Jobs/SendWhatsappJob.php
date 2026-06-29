<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsappJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public int $backoff = 30;

    public function __construct(
        public string $phone,
        public string $message,
    ) {}

    public function handle(): void
    {
        $base = (string) config('services.whatsapp_gateway.base');
        $timeout = (int) config('services.whatsapp_gateway.timeout', 10);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(3)
                ->retry(2, 200, function ($e) {
                    return $e instanceof ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->post(rtrim($base, '/').'/api/sendtext', [
                    'sessions' => 'session_1',
                    'target' => $this->phone,
                    'message' => $this->message,
                ]);

            if ($response->failed()) {
                Log::warning('SendWhatsappJob gateway non-2xx', [
                    'status' => $response->status(),
                    'phone' => $this->phone,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('WA Exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendWhatsappJob permanent failure', [
            'message' => $e->getMessage(),
            'phone' => $this->phone,
        ]);
    }
}

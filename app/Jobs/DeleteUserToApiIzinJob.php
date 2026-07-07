<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeleteUserToApiIzinJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $username,
    ) {}

    public function handle(): void
    {
        $base = rtrim((string) config('services.api_izin'), '/');
        $url = $base.'/global/user/delete/'.$this->username;

        try {
            $response = Http::timeout(15)->acceptJson()->delete($url);

            if ($response->failed()) {
                Log::error('Delete user di API Izin gagal', [
                    'username' => $this->username,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return;
            }

            Log::info('Delete user di API Izin sukses', [
                'username' => $this->username,
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Delete user di API Izin exception', [
                'username' => $this->username,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

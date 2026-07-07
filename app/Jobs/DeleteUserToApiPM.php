<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeleteUserToApiPM implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $user_id;

    /**
     * Create a new job instance.
     */
    public function __construct(int $user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $base = rtrim((string) config('services.api_project'), '/');
        $endpoint = ltrim((string) 'users/'.$this->user_id, '/');
        $url = $base.'/'.$endpoint;

        try {
            $response = Http::timeout(15)->acceptJson()->delete($url);

            if ($response->failed()) {
                Log::error('Delete user di API PM gagal', [
                    'user_id' => $this->user_id,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return;
            }

            Log::info('Delete user di API PM sukses', [
                'user_id' => $this->user_id,
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Delete user di API PM exception', [
                'user_id' => $this->user_id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

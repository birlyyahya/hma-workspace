<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterUserToApiProjectJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public User $user,
        public string $plainPassword,
    ) {}

    public function handle(): void
    {
        $base = rtrim((string) config('services.api_project'), '/');
        $endpoint = ltrim((string) 'auth/register', '/');
        $url = $base.'/'.$endpoint;

        $payload = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'username' => $this->user->username,
            'password' => $this->plainPassword,
        ];

        try {
            $response = Http::timeout(15)->acceptJson()->post($url, $payload);

            if ($response->failed()) {
                Log::error('Sync user ke API Project gagal', [
                    'user_id' => $this->user->id,
                    'username' => $this->user->username,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return;
            }

            Log::info('Sync user ke API Project sukses', [
                'user_id' => $this->user->id,
                'username' => $this->user->username,
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync user ke API Project exception', [
                'user_id' => $this->user->id,
                'username' => $this->user->username,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

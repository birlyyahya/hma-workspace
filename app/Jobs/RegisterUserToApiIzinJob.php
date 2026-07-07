<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder job — endpoint register di API Izin belum tersedia.
 * Saat endpoint sudah siap, ubah default `api_izin_register_endpoint` di config/services.php
 * (atau set env API_IZIN_REGISTER_ENDPOINT) lalu hapus early-return di bawah.
 */
class RegisterUserToApiIzinJob implements ShouldQueue
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
        // Log::info('Sync user ke API Izin (placeholder, endpoint belum aktif)', [
        //     'user_id' => $this->user->id,
        //     'username' => $this->user->username,
        // ]);

        // TODO: aktifkan blok di bawah ketika endpoint register API Izin sudah tersedia.
        // return;

        $base = rtrim((string) config('services.api_izin'), '/');
        $endpoint = ltrim('global/user/create', '/');
        $url = $base.'/'.$endpoint;

        $payload = [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'username' => $this->user->username,
            'role' => $this->user->role?->slug,
        ];

        try {
            $response = Http::timeout(15)->acceptJson()->post($url, $payload);

            if ($response->failed()) {
                Log::error('Sync user ke API Izin gagal', [
                    'user_id' => $this->user->id,
                    'username' => $this->user->username,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return;
            }

            Log::info('Sync user ke API Izin sukses', [
                'user_id' => $this->user->id,
                'username' => $this->user->username,
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync user ke API Izin exception', [
                'user_id' => $this->user->id,
                'username' => $this->user->username,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

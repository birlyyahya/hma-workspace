<?php

namespace App\Services;

use App\Mail\NotificationSpdMail;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Notifikasi otomatis (best-effort) — email di-queue supaya tidak menahan
     * request. Hasil kirim tercatat via hook send()/failed() di mailable;
     * di sini dicatat saat masuk antrean.
     */
    public static function send($user, $message, $spd)
    {
        // 🔹 jika ada nomor WA

        // $phone = '6285158551580';

        // SendWhatsappJob::dispatch($phone, $message);

        // 🔹 fallback email
        if (! empty($user->email)) {
            Mail::to($user->email)
                ->queue(new NotificationSpdMail($user, $spd));

            activity('izin')
                ->event('queued')
                ->withProperties(['spd_id' => $spd['id'] ?? null, 'email' => $user->email])
                ->log('Email notifikasi SPD #'.($spd['id'] ?? '-').' masuk antrean ke '.$user->email);

            return 'email';
        }

        return 'none';
    }

    /**
     * Kirim email SPD secara langsung (sinkron) — dipakai tombol "Kirim Email"
     * agar caller bisa menampilkan hasil pengiriman yang sebenarnya. Melempar
     * exception bila gagal; activity sent/failed dicatat oleh mailable.
     *
     * @param  array<string, mixed>  $spd
     */
    public static function sendSpdEmailNow($user, array $spd): void
    {
        if (empty($user->email)) {
            throw new \RuntimeException('Pegawai tidak memiliki alamat email.');
        }

        $mailable = new NotificationSpdMail($user, $spd);

        try {
            Mail::to($user->email)->send($mailable);
        } catch (\Throwable $e) {
            $mailable->failed($e);

            throw $e;
        }
    }
}

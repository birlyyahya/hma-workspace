<?php

namespace App\Services;

use App\Jobs\SendWhatsappJob;
use App\Mail\NotificationSpdMail;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public static function send($user, $message, $spd)
    {
        // 🔹 jika ada nomor WA

        $phone = '6285158551580';

        SendWhatsappJob::dispatch($phone, $message);

        // 🔹 fallback email
        if (! empty($user->email)) {
            Mail::to($user->email)
                ->queue(new NotificationSpdMail($user, $spd));

            return 'email';
        }

        return 'none';
    }
}

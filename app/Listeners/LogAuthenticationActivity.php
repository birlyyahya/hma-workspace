<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Mencatat aktivitas autentikasi (login/logout) ke log_name 'auth'.
 * Causer di-set eksplisit dari user event agar tetap tercatat walau
 * guard sudah tidak terisi saat logout.
 */
class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->event('login')
            ->log('Login');
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        activity('auth')
            ->causedBy($event->user)
            ->event('logout')
            ->log('Logout');
    }
}

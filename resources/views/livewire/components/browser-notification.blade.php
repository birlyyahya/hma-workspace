<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    #[On('push-subscribed')]
    public function subscribe(array $subscription): void
    {
        $endpoint = $subscription['endpoint'] ?? null;

        if (! $endpoint) {
            return;
        }

        Auth::user()->updatePushSubscription(
            $endpoint,
            $subscription['keys']['p256dh'] ?? null,
            $subscription['keys']['auth'] ?? null,
            'aes128gcm',
        );
    }
}; ?>

<div data-vapid-public-key="{{ config('webpush.vapid.public_key') }}" id="web-push-config"></div>

<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use NotificationChannels\WebPush\PushSubscription;

new class extends Component {
    #[On('push-subscribed')]
    public function subscribe(array $subscription): void
    {
        $endpoint = $subscription['endpoint'] ?? null;

        if (! $endpoint) {
            return;
        }

        $attributes = [
            'subscribable_id' => Auth::user()->getKey(),
            'subscribable_type' => Auth::user()->getMorphClass(),
            'public_key' => $subscription['keys']['p256dh'] ?? null,
            'auth_token' => $subscription['keys']['auth'] ?? null,
            'content_encoding' => 'aes128gcm',
            'user_agent' => request()->userAgent(),
        ];

        try {
            $this->persistSubscription($endpoint, $attributes);
        } catch (UniqueConstraintViolationException) {
            $this->persistSubscription($endpoint, $attributes);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function persistSubscription(string $endpoint, array $attributes): void
    {
        PushSubscription::query()
            ->firstOrNew(['endpoint' => $endpoint])
            ->forceFill($attributes)
            ->save();
    }
}; ?>

<div data-vapid-public-key="{{ config('webpush.vapid.public_key') }}" id="web-push-config"></div>

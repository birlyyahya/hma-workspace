<?php

namespace App\Livewire\Settings;

use App\Notifications\TestPushNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use NotificationChannels\WebPush\PushSubscription;

class Notifications extends Component
{
    public ?int $pendingDeleteDeviceId = null;

    public function getDeviceCountProperty(): int
    {
        return Auth::user()->pushSubscriptions()->count();
    }

    /**
     * @return array<int, array{id: int, name: string, service: string, endpoint: string, subscribed_at: string, last_seen: string}>
     */
    public function getDevicesProperty(): array
    {
        return Auth::user()->pushSubscriptions()
            ->latest('updated_at')
            ->get()
            ->map(fn (PushSubscription $subscription): array => [
                'id' => (int) $subscription->id,
                'name' => $this->describeDevice($subscription->user_agent),
                'service' => $this->describePushService($subscription->endpoint),
                'endpoint' => (string) $subscription->endpoint,
                'subscribed_at' => $subscription->created_at->translatedFormat('d M Y H:i'),
                'last_seen' => $subscription->updated_at->diffForHumans(),
            ])
            ->all();
    }

    public function getPendingDeleteDeviceNameProperty(): ?string
    {
        return collect($this->devices)->firstWhere('id', $this->pendingDeleteDeviceId)['name'] ?? null;
    }

    public function confirmRemoveDevice(int $subscriptionId): void
    {
        $this->pendingDeleteDeviceId = $subscriptionId;
        $this->modal('confirm-remove-device')->show();
    }

    public function removeDevice(): void
    {
        if ($this->pendingDeleteDeviceId === null) {
            return;
        }

        $deleted = Auth::user()->pushSubscriptions()->where('id', $this->pendingDeleteDeviceId)->delete();

        $this->pendingDeleteDeviceId = null;
        $this->modal('confirm-remove-device')->close();

        if ($deleted) {
            Toaster::success('Perangkat dihapus dari daftar penerima notifikasi.');
        }
    }

    public function sendTestNotification(): void
    {
        $user = Auth::user();

        if ($user->pushSubscriptions()->count() === 0) {
            Toaster::warning('Belum ada perangkat yang terdaftar. Aktifkan notifikasi dulu.');

            return;
        }

        $user->notifyNow(new TestPushNotification);

        Toaster::success('Notifikasi tes dikirim! Periksa perangkatmu.');
    }

    private function describeDevice(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'Perangkat tidak dikenal';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'OPR') => 'Opera',
            str_contains($userAgent, 'CriOS') => 'Chrome',
            str_contains($userAgent, 'FxiOS') => 'Firefox',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'Browser lain',
        };

        $platform = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $platform !== null ? "{$browser} di {$platform}" : $browser;
    }

    private function describePushService(string $endpoint): string
    {
        $host = (string) parse_url($endpoint, PHP_URL_HOST);

        return match (true) {
            str_contains($host, 'fcm.googleapis.com') => 'Google (Chrome/Android)',
            str_contains($host, 'push.apple.com') => 'Apple (iOS/macOS)',
            str_contains($host, 'mozilla.com') => 'Mozilla (Firefox)',
            str_contains($host, 'windows.com') => 'Microsoft (Edge)',
            default => $host,
        };
    }
}

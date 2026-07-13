<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\DarActivityClosed;
use App\Notifications\DarActivityCreated;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Kirim notifikasi DAR ke tim yang tergabung (team_user + pemilik task),
 * tanpa si pelaku aksi. Kegagalan notifikasi tidak boleh menggagalkan aksi utama.
 */
class DarNotifier
{
    /**
     * @param  array<string, mixed>  $activity
     */
    public function activityCreated(array $activity, int $actorId): void
    {
        $this->notify($activity, $actorId, fn (string $actorName) => new DarActivityCreated(
            activityId: (int) ($activity['id'] ?? 0),
            activityTitle: (string) ($activity['activity'] ?? ''),
            actorId: $actorId,
            actorName: $actorName,
        ));
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    public function activityClosed(array $activity, int $actorId): void
    {
        $this->notify($activity, $actorId, fn (string $actorName) => new DarActivityClosed(
            activityId: (int) ($activity['id'] ?? 0),
            activityTitle: (string) ($activity['activity'] ?? ''),
            actorId: $actorId,
            actorName: $actorName,
        ));
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function notify(array $activity, int $actorId, Closure $makeNotification): void
    {
        try {
            $recipients = $this->recipients($activity, $actorId);

            if ($recipients->isEmpty()) {
                return;
            }

            $actorName = (string) (User::find($actorId)?->name ?? 'Unknown');

            Notification::send($recipients, $makeNotification($actorName));
        } catch (\Throwable $e) {
            Log::warning('DAR notification failed', [
                'activity_id' => $activity['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * team_user boleh berupa list id atau list ['user_id' => ...] (dua bentuk payload API).
     *
     * @param  array<string, mixed>  $activity
     * @return Collection<int, User>
     */
    private function recipients(array $activity, int $actorId): Collection
    {
        $ids = collect($activity['team_user'] ?? [])
            ->map(fn ($member) => is_array($member) ? ($member['user_id'] ?? null) : $member)
            ->push($activity['user_id'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $actorId)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->get();
    }
}

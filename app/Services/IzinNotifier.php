<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\IzinHrdConfirmed;
use App\Notifications\IzinHrdRejected;
use App\Notifications\IzinSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notifikasi izin. Kegagalan notifikasi tidak boleh menggagalkan aksi utama.
 */
class IzinNotifier
{
    /**
     * Izin baru diajukan → beri tahu user yang berhak mengonfirmasi (izin.view.all / super-admin).
     *
     * @param  array<string, mixed>  $izin  keys: id (opsional), reason, start_date
     */
    public function submitted(array $izin, User $applicant): void
    {
        try {
            $recipients = $this->approvers()->reject(fn (User $user) => $user->is($applicant));

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new IzinSubmitted(
                izinId: isset($izin['id']) ? (int) $izin['id'] : null,
                applicantName: (string) $applicant->name,
                reason: (string) ($izin['reason'] ?? ''),
                startDate: $izin['start_date'] ?? null,
            ));
        } catch (\Throwable $e) {
            Log::warning('Izin submitted notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * HRD menyetujui izin → beri tahu pemohon.
     *
     * @param  array<string, mixed>  $izin  baris list atau detail izin
     */
    public function hrdConfirmed(array $izin, User $confirmer): void
    {
        $this->notifyApplicant($izin, $confirmer, fn (int $id) => new IzinHrdConfirmed(
            izinId: $id,
            confirmedBy: (string) $confirmer->name,
            reason: (string) ($izin['reason'] ?? ''),
            startDate: $izin['start_date'] ?? null,
        ));
    }

    /**
     * HRD menolak izin → beri tahu pemohon.
     *
     * @param  array<string, mixed>  $izin  baris list atau detail izin
     */
    public function hrdRejected(array $izin, User $rejecter): void
    {
        $this->notifyApplicant($izin, $rejecter, fn (int $id) => new IzinHrdRejected(
            izinId: $id,
            rejectedBy: (string) $rejecter->name,
            reason: (string) ($izin['reason'] ?? ''),
            startDate: $izin['start_date'] ?? null,
        ));
    }

    /**
     * @param  array<string, mixed>  $izin
     */
    private function notifyApplicant(array $izin, User $actor, \Closure $makeNotification): void
    {
        $izinId = (int) ($izin['id'] ?? 0);

        try {
            $applicant = $this->resolveApplicant($izin);

            if ($applicant === null) {
                Log::warning('Izin notification skipped: pemohon tidak ditemukan', ['izin_id' => $izinId]);

                return;
            }

            if ($applicant->is($actor)) {
                return;
            }

            $applicant->notify($makeNotification($izinId));
        } catch (\Throwable $e) {
            Log::warning('Izin HRD notification failed', ['izin_id' => $izinId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Baris list belum tentu memuat relasi users, jadi bila username tidak ada
     * ambil dari detail izin.
     *
     * @param  array<string, mixed>  $izin
     */
    private function resolveApplicant(array $izin): ?User
    {
        $username = data_get($izin, 'users.username') ?? data_get($izin, 'username');

        if (! $username && ! empty($izin['id'])) {
            $detail = app(IzinCache::class)->detail((int) $izin['id']);
            $username = data_get($detail, 'data.users.username');
        }

        return $username ? User::where('username', $username)->first() : null;
    }

    /**
     * @return Collection<int, User>
     */
    private function approvers(): Collection
    {
        return User::query()
            ->whereHas('role', fn ($role) => $role
                ->where('slug', 'super-admin')
                ->orWhereHas('permissions', fn ($permission) => $permission->where('name', 'izin.view.all')))
            ->get();
    }
}

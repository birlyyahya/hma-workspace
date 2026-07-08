<?php

use App\Models\User;
use App\Services\IzinCache;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Preview SPD'])]
class extends Component {
    public $id;

    public ?array $spd = null;

    public function mount(): void
    {
        $response = app(IzinCache::class)->spdList(['per_page' => 1000]);

        $rows = $response['data'] ?? [];
        $this->spd = collect($rows)->firstWhere('id', (int) $this->id);
    }

    /**
     * Kirim email SPD ke pegawai secara langsung (sinkron) agar hasil kirim
     * bisa ditampilkan apa adanya. Activity sent/failed dicatat di mailable.
     */
    public function sendEmail(): void
    {
        if (! Auth::user()?->can('spd.create')) {
            Toaster::error('Anda tidak memiliki izin mengirim email SPD.');

            return;
        }

        if (! $this->spd) {
            Toaster::error('Data SPD tidak tersedia.');

            return;
        }

        $user = User::find($this->spd['user_id'] ?? null);

        if (! $user) {
            Toaster::error('Pegawai SPD tidak ditemukan.');

            return;
        }

        try {
            NotificationService::sendSpdEmailNow($user, $this->spd);
            Toaster::success('Email SPD berhasil dikirim ke '.$user->email);
        } catch (\Throwable $e) {
            Log::error('Kirim email SPD gagal', ['spd_id' => $this->id, 'message' => $e->getMessage()]);
            Toaster::error('Gagal mengirim email SPD: '.$e->getMessage());
        }
    }
}; ?>

<div class="min-h-screen px-4 py-6">
    @if (! $spd)
    <div class="mx-auto max-w-2xl rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-zinc-200">
        <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-zinc-100 text-zinc-400">
            <flux:icon name="exclamation-triangle" class="h-6 w-6" />
        </div>
        <h1 class="text-lg font-semibold text-zinc-900">SPD tidak ditemukan</h1>
        <p class="mt-1 text-sm text-zinc-600">Data SPD dengan ID <span class="font-semibold">{{ $id }}</span> tidak tersedia.</p>
        <a href="{{ route('izin') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Izin
        </a>
    </div>
    @else
    @php
    $isSubmitted = (bool) ($spd['is_submitted'] ?? false);
    $isApproved = (bool) ($spd['is_approved'] ?? false);

    $idPadded = str_pad((string) ($spd['number'] ?? '0'), 2, '0', STR_PAD_LEFT);
    $monthRoman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][Carbon::now()->month - 1];
    $refNo = "HMA/IT RnD/SPD/{$idPadded}/{$monthRoman}/" . Carbon::now()->year;

    if ($isApproved) {
    $statusLabel = 'Disetujui';
    $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    } elseif ($isSubmitted) {
    $statusLabel = 'Menunggu Persetujuan Direktur';
    $statusClass = 'bg-amber-50 text-amber-800 ring-amber-200';
    } else {
    $statusLabel = 'Pending';
    $statusClass = 'bg-zinc-100 text-zinc-700 ring-zinc-200';
    }
    @endphp

    {{-- Toolbar --}}
    <div class="mx-auto mb-5 flex max-w-[900px] flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white px-5 py-3 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('izin') }}" class="inline-flex items-center gap-1.5 rounded-full bg-zinc-50 px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-100">
                <flux:icon name="arrow-left" class="h-4 w-4" />
                Kembali
            </a>
            <div class="hidden sm:block">
                <p class="text-sm font-semibold text-zinc-900">Preview SPD</p>
                <p class="text-xs text-zinc-500">{{ $refNo }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                {{ $statusLabel }}
            </span>
            @can('spd.create')
            <flux:button size="sm" icon="envelope" variant="primary" wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail">
                <span wire:loading.remove wire:target="sendEmail">Kirim Email</span>
                <span wire:loading wire:target="sendEmail">Mengirim...</span>
            </flux:button>
            @endcan
        </div>
    </div>

    {{-- PDF preview via URL stream (bukan data URI — lampiran gambar bisa berukuran MB,
         melebihi batas data-URL browser). Print & download dari kontrol bawaan viewer. --}}
    <div class="mx-auto max-w-[900px] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
        <iframe src="{{ route('izin.spd-pdf', $id) }}" class="h-[80vh] w-full" title="Preview SPD"></iframe>
    </div>
    @endif
</div>

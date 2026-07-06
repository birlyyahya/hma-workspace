<?php

use App\Models\User;
use App\Services\IzinCache;
use App\Services\SpdPdfComposer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Preview SPD'])]
class extends Component {
    public $id;

    public ?array $spd = null;

    public ?string $pdfPreview = null;

    public function mount(): void
    {
        $this->loadSpd();

        if ($this->spd) {
            $this->generatePdf();
        }
    }

    protected function loadSpd(): void
    {
        $response = app(IzinCache::class)->spdList(['per_page' => 1000]);

        $rows = $response['data'] ?? [];
        $this->spd = collect($rows)->firstWhere('id', (int) $this->id);
    }

    /**
     * Render PDF SPD (2 halaman utama + lampiran) lalu tampilkan sebagai data URI
     * base64 di iframe. Di-cache per-versi data agar tidak render ulang tiap kunjungan.
     */
    public function generatePdf(): void
    {
        if (! $this->spd) {
            return;
        }

        $key = 'spd-pdf-preview-'.$this->id.'-'.md5((string) json_encode($this->spd));

        $this->pdfPreview = Cache::remember($key, now()->addHours(6), function () {
            $user = User::find($this->spd['user_id'] ?? null);
            $bytes = app(SpdPdfComposer::class)->render($this->spd, $user);

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        });
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

        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
            {{ $statusLabel }}
        </span>
    </div>

    {{-- PDF preview (print & download tersedia dari kontrol bawaan viewer PDF) --}}
    <div class="mx-auto max-w-[900px] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
        @if ($pdfPreview)
        <iframe src="{{ $pdfPreview }}" class="h-[80vh] w-full" title="Preview SPD"></iframe>
        @else
        <div class="flex h-[60vh] flex-col items-center justify-center gap-3 text-zinc-400">
            <flux:icon name="document-text" class="h-10 w-10" />
            <span class="text-sm">PDF tidak dapat ditampilkan.</span>
            <flux:button size="sm" variant="primary" wire:click="generatePdf">Muat ulang PDF</flux:button>
        </div>
        @endif
    </div>
    @endif
</div>

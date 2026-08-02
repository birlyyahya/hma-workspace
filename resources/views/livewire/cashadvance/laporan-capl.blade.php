<?php

use App\Services\CaCache;
use App\Services\ProjectCache;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Laporan CA PL'])]
class extends Component
{
    /** @var array<string, mixed> */
    public array $balance = [];

    /** Project yang melibatkan user (PL pilih salah satu untuk judul laporan). */
    /** @var array<int, array{id: int, code: ?string, name: ?string}> */
    public array $projects = [];

    public ?int $selectedProjectId = null;

    /**
     * Diincrement lewat tombol "Generate Ulang" untuk memaksa iframe memuat
     * ulang PDF (cache-buster pada URL), terpisah dari cache PDF di server
     * yang sudah otomatis refresh sendiri saat data CA berubah.
     */
    public int $regenerateToken = 0;

    public function mount(?string $kodeCa = null): void
    {
        $userId = Auth::id();

        $this->projects = app(ProjectCache::class)->involvedProjects($userId);
        $this->selectedProjectId = $this->projects[0]['id'] ?? null;

        // walletPlByKode mengambil SATU wallet spesifik by kode (endpoint
        // singular); saat kodeCa tidak diberikan, jatuhkan ke daftar wallet
        // aktif user lalu ambil yang is_active (perilaku "laporan periode
        // berjalan" tanpa perlu tahu kode-nya).
        $this->balance = $kodeCa !== null
            ? app(CaCache::class)->walletPlByKode($kodeCa)['data'] ?? []
            : collect(app(CaCache::class)->DompetCaActiveTransaksi($userId))->firstWhere('is_active', 1) ?? [];
    }

    /**
     * Nama project terpilih untuk header; fallback ke judul_kegiatan bila
     * user tak terlibat project apa pun.
     */
    public function projectName(): string
    {
        $project = collect($this->projects)->firstWhere('id', $this->selectedProjectId);

        return $project['name'] ?? ($this->balance['judul_kegiatan'] ?? '-');
    }

    /**
     * URL PDF yang di-embed; berubah otomatis saat project dipilih ulang
     * (wire:model.live), dan bisa dipaksa refresh lewat tombol Generate Ulang.
     */
    public function pdfUrl(): ?string
    {
        if (empty($this->balance['kode_ca'])) {
            return null;
        }

        return route('cashadvance.laporan.capl.pdf', $this->balance['kode_ca']).'?'.http_build_query([
            'project_id' => $this->selectedProjectId,
            'v' => $this->regenerateToken,
        ]);
    }

    public function regenerate(): void
    {
        $this->regenerateToken++;
    }

    /**
     * Laporan ini bisa dibuka untuk periode aktif (dari ca-index) maupun
     * periode yang sudah tersegel (dari Riwayat Periode) — status ditampilkan
     * di header supaya jelas periode mana yang sedang dilihat.
     */
    public function isClosed(): bool
    {
        return ! ($this->balance['is_active'] ?? true);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-4 px-2 py-4 sm:px-4 sm:py-6">
    <div class="flex items-center gap-3 border-b border-zinc-200 pb-4 dark:border-zinc-800">
        <flux:button :href="route('cashadvance')" wire:navigate icon="arrow-left" variant="ghost" size="sm" />
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper">
            <flux:icon name="document-text" class="size-5" />
        </span>
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="lg" class="font-bold">Laporan CA PL</flux:heading>
                @if (! empty($balance['kode_ca']))
                @if ($this->isClosed())
                <flux:badge size="sm" color="zinc">Ditutup</flux:badge>
                @else
                <flux:badge size="sm" color="green">Aktif</flux:badge>
                @endif
                @endif
            </div>
            <flux:text class="text-xs">{{ $balance['kode_ca'] ?? '—' }}</flux:text>
        </div>
    </div>

    @if (empty($balance['kode_ca']))
    <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <flux:icon name="exclamation-triangle" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
        <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Dompet CA PL tidak ditemukan.</p>
    </div>
    @else
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex-1">
                <label class="mb-1.5 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Judul laporan (pilih project)</label>
                <x-search-select
                    model="selectedProjectId"
                    :live="true"
                    :options="collect($projects)->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name']])->all()"
                    placeholder="Pilih project..."
                    searchPlaceholder="Cari project..." />
            </div>
            <flux:button wire:click="regenerate" variant="primary" icon="arrow-path" size="sm" class="bg-ca-copper! hover:bg-ca-copper-deep!">
                Generate Ulang
            </flux:button>
        </div>
        <p class="mt-2 text-xs text-zinc-400">Pilih project pengeluaran terbesar sebagai judul dokumen.</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <iframe wire:key="ca-pl-pdf-{{ $regenerateToken }}" src="{{ $this->pdfUrl() }}" class="h-[80vh] w-full" title="Laporan CA PL"></iframe>
    </div>
    @endif
</div>

<?php

use App\Services\CaCache;
use App\Services\ProjectCache;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Laporan CA Kegiatan'])]
class extends Component
{
    public string $kodeCa;

    /** @var array<string, mixed> */
    public array $wallet = [];

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

    public function mount(string $kodeCa): void
    {
        $this->kodeCa = $kodeCa;

        $this->projects = app(ProjectCache::class)->involvedProjects(Auth::id());
        $this->selectedProjectId = $this->projects[0]['id'] ?? null;

        $this->wallet = app(CaCache::class)->walletKegiatanByKode($kodeCa)['data'] ?? [];

        abort_if(empty($this->wallet), 404);
        abort_unless($this->canView(), 403);
    }

    /**
     * Pemilik, kolaborator, atau role dengan scope cashadvance 'all' (mis.
     * finance) boleh membuka laporan dompet kegiatan ini.
     */
    private function canView(): bool
    {
        $isOwner = (int) ($this->wallet['user_id'] ?? 0) === (int) Auth::id();

        $isCollaborator = collect($this->wallet['collaborators'] ?? [])
            ->contains(fn (array $c): bool => (int) $c['user_id'] === (int) Auth::id());

        return $isOwner || $isCollaborator || Auth::user()->viewScopeFor('cashadvance') === 'all';
    }

    /**
     * Nama project terpilih untuk header; fallback ke judul_kegiatan bila
     * user tak terlibat project apa pun.
     */
    public function projectName(): string
    {
        $project = collect($this->projects)->firstWhere('id', $this->selectedProjectId);

        return $project['name'] ?? ($this->wallet['judul_kegiatan'] ?? '-');
    }

    /**
     * URL PDF yang di-embed; berubah otomatis saat project dipilih ulang
     * (wire:model.live), dan bisa dipaksa refresh lewat tombol Generate Ulang.
     */
    public function pdfUrl(): ?string
    {
        if (empty($this->wallet['kode_ca'])) {
            return null;
        }

        return route('cashadvance.laporan.kegiatan.pdf', $this->wallet['kode_ca']).'?'.http_build_query([
            'project_id' => $this->selectedProjectId,
            'v' => $this->regenerateToken,
        ]);
    }

    public function regenerate(): void
    {
        $this->regenerateToken++;
    }

    /**
     * Laporan ini bisa dibuka untuk dompet kegiatan yang masih aktif maupun
     * yang sudah ditutup — status ditampilkan di header supaya jelas dompet
     * mana yang sedang dilihat.
     */
    public function isClosed(): bool
    {
        return ! ($this->wallet['is_active'] ?? true);
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
                <flux:heading size="lg" class="font-bold">Laporan CA Kegiatan</flux:heading>
                @if (! empty($wallet['kode_ca']))
                @if ($this->isClosed())
                <flux:badge size="sm" color="zinc">Ditutup</flux:badge>
                @else
                <flux:badge size="sm" color="green">Aktif</flux:badge>
                @endif
                @endif
            </div>
            <flux:text class="text-xs">{{ $wallet['judul_kegiatan'] ?? $kodeCa }} &middot; {{ $wallet['kode_ca'] ?? $kodeCa }}</flux:text>
        </div>
    </div>

    @if (empty($wallet['kode_ca']))
    <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <flux:icon name="exclamation-triangle" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
        <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Dompet kegiatan tidak ditemukan.</p>
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
            <flux:button wire:click="regenerate" variant="primary" icon="arrow-path" size="sm" class="bg-ca-copper! hover:bg-ca-copper-deep!"
                wire:loading.attr="disabled" wire:target="regenerate">
                <span wire:loading.remove wire:target="regenerate">Generate Ulang</span>
                <span wire:loading wire:target="regenerate">Memuat&hellip;</span>
            </flux:button>
        </div>
        <p class="mt-2 text-xs text-zinc-400">Pilih project pengeluaran terbesar sebagai judul dokumen.</p>
    </div>

    {{-- wire:key di wrapper (bukan cuma iframe) supaya x-data ikut fresh --}}
    {{-- setiap Generate Ulang, jadi spinner otomatis muncul lagi. --}}
    <div wire:key="ca-kegiatan-pdf-{{ $regenerateToken }}" x-data="{ loading: true }"
        class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div x-show="loading" x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-white/90 dark:bg-zinc-900/90">
            <span class="size-8 animate-spin rounded-full border-2 border-ca-copper/25 border-t-ca-copper"></span>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Menyusun laporan PDF&hellip;</p>
        </div>
        <iframe x-on:load="loading = false" src="{{ $this->pdfUrl() }}" class="h-[80vh] w-full" title="Laporan CA Kegiatan"></iframe>
    </div>
    @endif
</div>

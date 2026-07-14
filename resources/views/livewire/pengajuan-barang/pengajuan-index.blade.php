<?php

use App\Services\PengajuanBarangDummy;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<int, array<string, mixed>> Seluruh pengajuan (dummy) */
    public array $pengajuan = [];

    /** @var array<string, array{label: string, color: string}> */
    public array $statuses = [];

    public string $search = '';

    public string $status = '';

    public string $kategori = '';

    public function mount(PengajuanBarangDummy $dummy): void
    {
        $this->pengajuan = $dummy->pengajuanList();
        $this->statuses = $dummy->statuses();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Pengajuan setelah filter search/status/kategori + total estimasi terhitung.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function filteredPengajuan(): array
    {
        $dummy = app(PengajuanBarangDummy::class);

        return collect($this->pengajuan)
            ->when($this->search !== '', fn ($items) => $items->filter(
                fn (array $p): bool => str_contains(strtolower($p['kode']), strtolower($this->search))
                    || str_contains(strtolower($p['keperluan']), strtolower($this->search))
                    || str_contains(strtolower($p['pemohon']), strtolower($this->search))
                    || str_contains(strtolower($p['project_name'] ?? ''), strtolower($this->search))
            ))
            ->when($this->status !== '', fn ($items) => $items->where('status', $this->status))
            ->when($this->kategori !== '', fn ($items) => $items->where('kategori', $this->kategori))
            ->map(function (array $p) use ($dummy): array {
                $p['total_estimasi'] = $dummy->totalEstimasi($p);

                return $p;
            })
            ->values()
            ->all();
    }

    /**
     * Jumlah pengajuan per status (untuk kartu statistik & pill filter).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $byStatus = collect($this->pengajuan)->countBy('status');

        return [
            'total' => count($this->pengajuan),
            'diajukan' => (int) $byStatus->get('diajukan', 0),
            'disetujui' => (int) $byStatus->get('disetujui', 0),
            'ditolak' => (int) $byStatus->get('ditolak', 0),
            'selesai' => (int) $byStatus->get('selesai', 0),
        ];
    }
}; ?>

<div class="space-y-4 md:space-y-5">

    {{-- ================= STATS ================= --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 md:gap-4">
        {{-- TOTAL (kartu aksen) --}}
        <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-zinc-900 via-zinc-800 to-zinc-900 dark:from-zinc-800 dark:via-zinc-900 dark:to-black p-4 md:p-5 text-white shadow-sm">
            <div class="pointer-events-none absolute -right-8 -top-10 size-32 rounded-full bg-red-500/20 blur-2xl"></div>
            <div class="relative space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-white/70">Total Pengajuan</p>
                    <flux:icon name="clipboard-document-list" class="size-5 text-white/50" />
                </div>
                <p class="text-3xl font-semibold tabular-nums">{{ $this->stats()['total'] }}</p>
                <p class="text-xs text-white/60">Seluruh pengajuan tercatat</p>
            </div>
        </div>

        {{-- MENUNGGU --}}
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 md:p-5 shadow-sm">
            <div class="pointer-events-none absolute -right-8 -top-10 size-32 rounded-full bg-yellow-400/15 blur-2xl"></div>
            <div class="relative space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Menunggu</p>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-500/15">
                        <flux:icon name="clock" class="size-4 text-yellow-600 dark:text-yellow-400" />
                    </span>
                </div>
                <p class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->stats()['diajukan'] }}</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">Butuh persetujuan</p>
            </div>
        </div>

        {{-- DISETUJUI --}}
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 md:p-5 shadow-sm">
            <div class="pointer-events-none absolute -right-8 -top-10 size-32 rounded-full bg-green-400/15 blur-2xl"></div>
            <div class="relative space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Disetujui</p>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-500/15">
                        <flux:icon name="check-circle" class="size-4 text-green-600 dark:text-green-400" />
                    </span>
                </div>
                <p class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->stats()['disetujui'] }}</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">Siap diproses pembelian</p>
            </div>
        </div>

        {{-- DITOLAK --}}
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 md:p-5 shadow-sm">
            <div class="pointer-events-none absolute -right-8 -top-10 size-32 rounded-full bg-red-400/15 blur-2xl"></div>
            <div class="relative space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Ditolak</p>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-red-100 dark:bg-red-500/15">
                        <flux:icon name="x-circle" class="size-4 text-red-600 dark:text-red-400" />
                    </span>
                </div>
                <p class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->stats()['ditolak'] }}</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">Perlu pengajuan ulang</p>
            </div>
        </div>
    </div>

    {{-- ================= FILTER + LIST ================= --}}
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm">

        {{-- FILTER BAR --}}
        <div class="space-y-3 border-b border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari kode, keperluan, pemohon, atau project..." class="md:max-w-md" />
                <flux:select wire:model.live="kategori" size="sm" class="md:max-w-44">
                    <option value="">Semua Kategori</option>
                    <option value="project">Project</option>
                    <option value="non_project">Non-Project</option>
                </flux:select>
            </div>

            {{-- STATUS PILLS --}}
            <div class="flex gap-2 overflow-x-auto pb-1 -mb-1">
                <button type="button" wire:click="setStatus('')"
                    class="shrink-0 cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors {{ $status === '' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    Semua
                    <span class="ms-1 tabular-nums opacity-60">{{ $this->stats()['total'] }}</span>
                </button>
                @foreach ($statuses as $key => $s)
                    <button type="button" wire:click="setStatus('{{ $key }}')" wire:key="pill-{{ $key }}"
                        class="shrink-0 cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors {{ $status === $key ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                        <span class="me-1 inline-block size-1.5 rounded-full {{ match ($key) {
                            'diajukan' => 'bg-yellow-500',
                            'disetujui' => 'bg-green-500',
                            'ditolak' => 'bg-red-500',
                            'selesai' => 'bg-blue-500',
                            default => 'bg-zinc-400',
                        } }}"></span>
                        {{ $s['label'] }}
                        <span class="ms-1 tabular-nums opacity-60">{{ $this->stats()[$key] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- DESKTOP TABLE --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3 font-medium">Pengajuan</th>
                        <th class="px-4 py-3 font-medium">Keperluan</th>
                        <th class="px-4 py-3 font-medium">Pemohon</th>
                        <th class="px-4 py-3 font-medium text-center">Item</th>
                        <th class="px-4 py-3 font-medium text-right">Estimasi</th>
                        <th class="px-4 py-3 font-medium">Dibutuhkan</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-2 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->filteredPengajuan() as $p)
                        <tr wire:key="pengajuan-{{ $p['kode'] }}" class="group border-b border-zinc-100 dark:border-zinc-800 last:border-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $p['kategori'] === 'project' ? 'bg-purple-100 dark:bg-purple-500/15' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                                        <flux:icon :name="$p['kategori'] === 'project' ? 'folder' : 'shopping-bag'" class="size-4 {{ $p['kategori'] === 'project' ? 'text-purple-600 dark:text-purple-400' : 'text-zinc-500 dark:text-zinc-400' }}" />
                                    </span>
                                    <div>
                                        <a href="{{ route('pengajuan-barang.show', $p['kode']) }}" wire:navigate class="font-semibold text-zinc-900 dark:text-white hover:text-red-700 dark:hover:text-red-400">
                                            {{ $p['kode'] }}
                                        </a>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $p['kategori'] === 'project' ? 'Project' : 'Non-Project' }}
                                            • {{ \Carbon\Carbon::parse($p['created_at'])->translatedFormat('d M') }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 max-w-64">
                                <p class="truncate text-zinc-900 dark:text-white">{{ $p['keperluan'] }}</p>
                                @if ($p['project_name'])
                                    <p class="mt-0.5 inline-flex max-w-full items-center gap-1 rounded-full bg-purple-50 dark:bg-purple-500/10 px-2 py-0.5 text-xs text-purple-700 dark:text-purple-300">
                                        <flux:icon name="folder" class="size-3 shrink-0" />
                                        <span class="truncate">{{ $p['project_name'] }}</span>
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <flux:avatar size="xs" :name="$p['pemohon']" />
                                    <div>
                                        <p class="text-zinc-900 dark:text-white">{{ $p['pemohon'] }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $p['department'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2.5 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:text-zinc-300">
                                    {{ count($p['items']) }} item
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right font-medium tabular-nums text-zinc-900 dark:text-white">
                                Rp {{ number_format($p['total_estimasi'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-zinc-600 dark:text-zinc-300">
                                {{ \Carbon\Carbon::parse($p['tanggal_dibutuhkan'])->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3.5">
                                <flux:badge size="sm" :color="$statuses[$p['status']]['color']">{{ $statuses[$p['status']]['label'] }}</flux:badge>
                            </td>
                            <td class="px-2 py-3.5">
                                <a href="{{ route('pengajuan-barang.show', $p['kode']) }}" wire:navigate>
                                    <flux:icon name="chevron-right" class="size-4 text-zinc-300 group-hover:text-zinc-500 dark:text-zinc-600 dark:group-hover:text-zinc-400 transition-colors" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-14 text-center">
                                <flux:icon name="inbox" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Tidak ada pengajuan yang cocok dengan filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE CARDS --}}
        <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->filteredPengajuan() as $p)
                <a href="{{ route('pengajuan-barang.show', $p['kode']) }}" wire:navigate wire:key="pengajuan-m-{{ $p['kode'] }}"
                    class="relative block p-4 ps-5 space-y-2 active:bg-zinc-50 dark:active:bg-zinc-800/50">
                    <span class="absolute inset-y-3 start-0 w-1 rounded-e-full {{ match ($p['status']) {
                        'diajukan' => 'bg-yellow-400',
                        'disetujui' => 'bg-green-500',
                        'ditolak' => 'bg-red-500',
                        'selesai' => 'bg-blue-500',
                        default => 'bg-zinc-300',
                    } }}"></span>
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $p['kode'] }}</span>
                        <flux:badge size="sm" :color="$statuses[$p['status']]['color']">{{ $statuses[$p['status']]['label'] }}</flux:badge>
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $p['keperluan'] }}</p>
                    @if ($p['project_name'])
                        <p class="inline-flex max-w-full items-center gap-1 rounded-full bg-purple-50 dark:bg-purple-500/10 px-2 py-0.5 text-xs text-purple-700 dark:text-purple-300">
                            <flux:icon name="folder" class="size-3 shrink-0" />
                            <span class="truncate">{{ $p['project_name'] }}</span>
                        </p>
                    @endif
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1">
                            <flux:avatar size="xs" :name="$p['pemohon']" />
                            {{ $p['pemohon'] }}
                        </span>
                        <span>{{ count($p['items']) }} item</span>
                        <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-300">Rp {{ number_format($p['total_estimasi'], 0, ',', '.') }}</span>
                        <span>Butuh {{ \Carbon\Carbon::parse($p['tanggal_dibutuhkan'])->translatedFormat('d M Y') }}</span>
                    </div>
                </a>
            @empty
                <div class="p-8 text-center">
                    <flux:icon name="inbox" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Tidak ada pengajuan yang cocok dengan filter.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

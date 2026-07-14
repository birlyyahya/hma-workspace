<?php

use App\Services\PengajuanBarangDummy;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Detail Pengajuan Barang'])]
class extends Component {
    /** @var array<string, mixed> */
    public array $pengajuan = [];

    /** @var array<string, array{label: string, color: string}> */
    public array $statuses = [];

    public int $totalEstimasi = 0;

    public function mount(string $kode, PengajuanBarangDummy $dummy): void
    {
        $pengajuan = $dummy->pengajuanByKode($kode);

        abort_if($pengajuan === null, 404);

        $this->pengajuan = $pengajuan;
        $this->statuses = $dummy->statuses();
        $this->totalEstimasi = $dummy->totalEstimasi($pengajuan);
    }
}; ?>

<div class="bg-zinc-50/50 min-h-screen">
    <div class="max-w-4xl mx-auto px-2 py-4 space-y-4 md:space-y-6">

        {{-- ================= HEADER ================= --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('pengajuan-barang') }}" wire:navigate>
                    <flux:button size="sm" variant="ghost" icon="arrow-left" class="cursor-pointer" />
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $pengajuan['kode'] }}</flux:heading>
                        <flux:badge size="sm" :color="$statuses[$pengajuan['status']]['color']">
                            {{ $statuses[$pengajuan['status']]['label'] }}
                        </flux:badge>
                    </div>
                    <flux:text class="text-sm">
                        Diajukan {{ \Carbon\Carbon::parse($pengajuan['created_at'])->translatedFormat('d M Y') }} oleh {{ $pengajuan['pemohon'] }}
                    </flux:text>
                </div>
            </div>

            <flux:button size="sm" variant="outline" icon="printer" class="cursor-pointer" disabled>
                Cetak (segera)
            </flux:button>
        </div>

        {{-- ================= INFO ================= --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 sm:p-6 space-y-4">
            <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Informasi Pengajuan</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Kategori</p>
                    <div class="mt-1">
                        <flux:badge size="sm" :color="$pengajuan['kategori'] === 'project' ? 'purple' : 'zinc'">
                            {{ $pengajuan['kategori'] === 'project' ? 'Project' : 'Non-Project' }}
                        </flux:badge>
                    </div>
                </div>
                @if ($pengajuan['project_name'])
                    <div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Project</p>
                        <p class="mt-1 text-sm text-zinc-900 dark:text-white">{{ $pengajuan['project_name'] }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Pemohon</p>
                    <p class="mt-1 text-sm text-zinc-900 dark:text-white">{{ $pengajuan['pemohon'] }} — {{ $pengajuan['department'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Tanggal Dibutuhkan</p>
                    <p class="mt-1 text-sm text-zinc-900 dark:text-white">
                        {{ \Carbon\Carbon::parse($pengajuan['tanggal_dibutuhkan'])->translatedFormat('d M Y') }}
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Keperluan</p>
                    <p class="mt-1 text-sm text-zinc-900 dark:text-white">{{ $pengajuan['keperluan'] }}</p>
                </div>
            </div>
        </div>

        {{-- ================= RIWAYAT STATUS ================= --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 sm:p-6 space-y-4">
            <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Riwayat Status</flux:heading>

            <ol>
                @foreach ($pengajuan['history'] as $index => $entry)
                    @php
                        [$icon, $dotClass] = match ($entry['status']) {
                            'diajukan' => ['paper-airplane', 'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/15 dark:text-yellow-400'],
                            'disetujui' => ['check', 'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-400'],
                            'ditolak' => ['x-mark', 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400'],
                            'selesai' => ['check-badge', 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400'],
                            default => ['clock', 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
                        };
                        $isLast = $index === count($pengajuan['history']) - 1;
                    @endphp
                    <li wire:key="history-{{ $index }}" class="relative flex gap-3 {{ $isLast ? '' : 'pb-6' }}">
                        @unless ($isLast)
                            <span class="absolute start-4 top-8 bottom-0 w-px bg-zinc-200 dark:bg-zinc-700"></span>
                        @endunless
                        <span class="relative flex size-8 shrink-0 items-center justify-center rounded-full {{ $dotClass }}">
                            <flux:icon :name="$icon" class="size-4" />
                        </span>
                        <div class="min-w-0 pt-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-sm font-medium {{ $isLast ? 'text-zinc-900 dark:text-white' : 'text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $statuses[$entry['status']]['label'] ?? ucfirst($entry['status']) }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    oleh {{ $entry['by'] }} • {{ \Carbon\Carbon::parse($entry['at'])->translatedFormat('d M Y H:i') }}
                                </span>
                            </div>
                            @if ($entry['catatan'])
                                <p class="mt-1.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $entry['catatan'] }}
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- ================= DAFTAR BARANG ================= --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-4 sm:p-6 pb-0!">
                <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Daftar Barang ({{ count($pengajuan['items']) }} item)
                </flux:heading>
            </div>

            {{-- DESKTOP TABLE --}}
            <div class="hidden sm:block overflow-x-auto p-4 sm:p-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-4 font-medium">#</th>
                            <th class="py-2 pe-4 font-medium">Nama Barang</th>
                            <th class="py-2 pe-4 font-medium">Spesifikasi</th>
                            <th class="py-2 pe-4 font-medium text-center">Qty</th>
                            <th class="py-2 pe-4 font-medium text-right">Estimasi /satuan</th>
                            <th class="py-2 font-medium text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pengajuan['items'] as $index => $item)
                            <tr wire:key="item-{{ $index }}" class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                <td class="py-3 pe-4 text-zinc-500 dark:text-zinc-400">{{ $index + 1 }}</td>
                                <td class="py-3 pe-4 font-medium text-zinc-900 dark:text-white">{{ $item['nama_barang'] }}</td>
                                <td class="py-3 pe-4 text-zinc-600 dark:text-zinc-300">{{ $item['spesifikasi'] ?: '—' }}</td>
                                <td class="py-3 pe-4 text-center tabular-nums">{{ $item['qty'] }} {{ $item['satuan'] }}</td>
                                <td class="py-3 pe-4 text-right tabular-nums">Rp {{ number_format($item['estimasi_harga'], 0, ',', '.') }}</td>
                                <td class="py-3 text-right tabular-nums">Rp {{ number_format($item['qty'] * $item['estimasi_harga'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="pt-3 text-right font-medium">Total Estimasi</td>
                            <td class="pt-3 text-right text-base font-semibold tabular-nums text-zinc-900 dark:text-white">
                                Rp {{ number_format($totalEstimasi, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- MOBILE CARDS --}}
            <div class="sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800 p-4 pt-3 space-y-0">
                @foreach ($pengajuan['items'] as $index => $item)
                    <div wire:key="item-m-{{ $index }}" class="py-3 space-y-1">
                        <p class="font-medium text-sm text-zinc-900 dark:text-white">{{ $item['nama_barang'] }}</p>
                        @if ($item['spesifikasi'])
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item['spesifikasi'] }}</p>
                        @endif
                        <div class="flex items-center justify-between text-sm">
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ $item['qty'] }} {{ $item['satuan'] }} × Rp {{ number_format($item['estimasi_harga'], 0, ',', '.') }}</span>
                            <span class="tabular-nums font-medium">Rp {{ number_format($item['qty'] * $item['estimasi_harga'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
                <div class="flex items-center justify-between pt-3">
                    <span class="text-sm font-medium">Total Estimasi</span>
                    <span class="text-base font-semibold tabular-nums text-zinc-900 dark:text-white">
                        Rp {{ number_format($totalEstimasi, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ================= LAMPIRAN ================= --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 sm:p-6 space-y-4">
            <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Dokumen Lampiran ({{ count($pengajuan['lampiran']) }})
            </flux:heading>

            @forelse ($pengajuan['lampiran'] as $index => $file)
                <div wire:key="lampiran-{{ $index }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2.5">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $file['type'] === 'pdf' ? 'bg-red-100 dark:bg-red-500/15' : 'bg-blue-100 dark:bg-blue-500/15' }}">
                            <flux:icon :name="$file['type'] === 'pdf' ? 'document-text' : 'photo'" class="size-4 {{ $file['type'] === 'pdf' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $file['name'] }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $file['size'] }}</p>
                        </div>
                    </div>
                    <flux:button size="xs" variant="ghost" icon="arrow-down-tray" class="cursor-pointer" disabled />
                </div>
            @empty
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Tidak ada lampiran.</p>
            @endforelse
        </div>
    </div>
</div>

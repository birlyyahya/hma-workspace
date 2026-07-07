<?php

use App\Services\InventarisCache;
use Livewire\Volt\Component;

new class extends Component {
    public int $id;

    /** @var array<string, mixed> Detail item inventaris dari API */
    public array $item = [];

    public function mount(int $id): void
    {
        $this->id = $id;
        $this->item = app(InventarisCache::class)->detail($id)['data'] ?? [];
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-lg mx-auto px-4 sm:px-6 py-6 space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('inventaris') }}" wire:navigate>
                    <flux:button size="sm" variant="outline" icon="arrow-left">Kembali</flux:button>
                </a>
                <flux:heading size="lg">Detail Inventaris</flux:heading>
            </div>
        </div>

        @if ($item === [])
            <div class="rounded-xl border border-zinc-200 bg-white p-12 text-center text-zinc-400 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500">
                <div class="flex flex-col items-center gap-2">
                    <flux:icon name="archive-box" class="size-8" />
                    <span>Data inventaris tidak ditemukan.</span>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kode</dt>
                        <dd class="mt-1 font-mono text-sm text-zinc-800 dark:text-zinc-100">{{ $item['kode'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Nama</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item['nama'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kategori</dt>
                        <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $item['kategori'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Lokasi</dt>
                        <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $item['lokasi'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kondisi</dt>
                        <dd class="mt-1">
                            <flux:badge size="sm" :color="match ($item['kondisi'] ?? '') {
                                'baik' => 'green',
                                'rusak ringan' => 'yellow',
                                'rusak berat' => 'red',
                                default => 'zinc',
                            }">
                                {{ ucfirst($item['kondisi'] ?? '-') }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Keterangan</dt>
                        <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $item['keterangan'] ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
</div>

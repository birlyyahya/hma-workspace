<?php

use App\Services\InventarisCache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<string, mixed>|null Respons list dari API (data + pagination) */
    public ?array $data = null;

    public int $page = 1;

    public int $perPage = 10;

    public string $search = '';

    public string $sort = 'desc';

    public function mount(): void
    {
        $this->fetchData();
    }

    public function goToPage(int $page): void
    {
        $this->page = $page;
        $this->fetchData();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedSort(): void
    {
        $this->page = 1;
        $this->fetchData();
    }

    #[On('inventarisSaved')]
    public function fetchData(): void
    {
        $this->data = app(InventarisCache::class)->list($this->buildQuery());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuery(): array
    {
        return array_filter([
            'page' => $this->page,
            'per_page' => $this->perPage,
            'search' => $this->search,
            'sort' => $this->sort,
        ], fn ($value) => $value !== '' && $value !== null);
    }
} ?>

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            placeholder="Cari item inventaris..."
            class="sm:max-w-xs"
        />

        <flux:select wire:model.live="sort" class="sm:max-w-40">
            <flux:select.option value="desc">Terbaru</flux:select.option>
            <flux:select.option value="asc">Terlama</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Lokasi</th>
                    <th class="px-4 py-3">Kondisi</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                @forelse ($data['data'] ?? [] as $item)
                    <tr wire:key="inventaris-{{ $item['id'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $item['kode'] ?? '-' }}</td>
                        <td class="px-4 py-3 font-medium text-zinc-800 dark:text-zinc-100">{{ $item['nama'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $item['kategori'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $item['lokasi'] ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="match ($item['kondisi'] ?? '') {
                                'baik' => 'green',
                                'rusak ringan' => 'yellow',
                                'rusak berat' => 'red',
                                default => 'zinc',
                            }">
                                {{ ucfirst($item['kondisi'] ?? '-') }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('inventaris.show', $item['id']) }}" wire:navigate>
                                <flux:button size="xs" variant="outline" icon="eye">Detail</flux:button>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-400 dark:text-zinc-500">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="archive-box" class="size-8" />
                                <span>Belum ada data inventaris.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @php
        $pagination = $data['pagination'] ?? [];
        $lastPage = (int) ($pagination['last_page'] ?? 1);
    @endphp
    @if ($lastPage > 1)
        <div class="flex items-center justify-between">
            <flux:text size="sm">
                Halaman {{ $page }} dari {{ $lastPage }}
            </flux:text>
            <div class="flex gap-2">
                <flux:button size="sm" variant="outline" icon="chevron-left" :disabled="$page <= 1" wire:click="goToPage({{ $page - 1 }})" />
                <flux:button size="sm" variant="outline" icon="chevron-right" :disabled="$page >= $lastPage" wire:click="goToPage({{ $page + 1 }})" />
            </div>
        </div>
    @endif
</div>

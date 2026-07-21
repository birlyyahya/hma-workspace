<?php

use App\Services\InventarisDummy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Pilih Project'])]
class extends Component {
    public string $search = '';

    public string $filter = '';

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function projects(): array
    {
        $projects = app(InventarisDummy::class)->projects();

        if ($this->filter === '') {
            return $projects;
        }

        return array_values(array_filter(
            $projects,
            fn (array $p) => str_contains(strtolower($p['name'].' '.$p['company']), strtolower($this->filter)),
        ));
    }

    /**
     * Cari unit lintas project berdasarkan SN atau kode unit, lalu buka detailnya.
     */
    public function searchSn(): void
    {
        $unit = app(InventarisDummy::class)->findBySnOrCode($this->search);

        if ($unit === null) {
            Toaster::warning('SN / kode unit "'.$this->search.'" tidak ditemukan.');

            return;
        }

        $this->redirectRoute('inventaris.unit-detail', ['unitId' => $unit['id']], navigate: true);
    }

    public function resetDemo(): void
    {
        app(InventarisDummy::class)->reset();
        unset($this->projects);
        Toaster::success('Data demo inventaris dikembalikan ke kondisi awal.');
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        {{-- HERO HEADER --}}
        <div class="relative overflow-hidden rounded-2xl border border-white/15 bg-linear-to-br from-red-700 via-red-600 to-red-600 px-6 sm:px-8 py-6 text-white shadow-sm">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -right-20 -top-24 size-72 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -left-24 -bottom-28 size-72 rounded-full bg-black/15 blur-3xl"></div>
            </div>

            <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="min-w-0 space-y-3">
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white/90 ring-1 ring-white/20 backdrop-blur">
                        <flux:icon name="archive-box" class="size-3.5" />
                        <span>Workspace</span>
                        <span class="opacity-50">/</span>
                        <span>Inventaris</span>
                    </div>
                    <div class="space-y-1">
                        <flux:heading size="xl" class="text-white leading-tight">
                            Inventaris
                        </flux:heading>
                        <p class="text-sm text-white/80">
                            Pilih project untuk mengelola unit barang, serial number, dan pergerakannya.
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto md:gap-3">
                    <flux:button icon="arrow-path" variant="ghost" wire:click="resetDemo" class="cursor-pointer w-full sm:w-auto text-white! hover:bg-white/10!">
                        Reset Demo
                    </flux:button>
                    <a href="{{ route('inventaris.scan') }}" wire:navigate class="w-full sm:w-auto">
                        <flux:button icon="qr-code" variant="primary" class="cursor-pointer w-full sm:w-auto bg-white! text-red-700! hover:bg-white/90!">
                            Scan SN
                        </flux:button>
                    </a>
                </div>
            </div>
        </div>

        {{-- SEARCH SN GLOBAL + FILTER PROJECT --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form wire:submit="searchSn" class="w-full sm:max-w-md">
                <flux:input wire:model="search" icon="qr-code" placeholder="Cari SN / kode unit lalu Enter..." kbd="↵" />
            </form>
            <flux:input wire:model.live.debounce.300ms="filter" icon="magnifying-glass" placeholder="Filter project..." class="sm:max-w-xs" />
        </div>

        {{-- PROJECT CARDS --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->projects as $project)
                @php
                    $snPct = $project['total_units'] > 0 ? round($project['sn_units'] / $project['total_units'] * 100) : 0;
                    $genOnlyPct = $project['total_units'] > 0 ? round(($project['generated_units'] - $project['sn_units']) / $project['total_units'] * 100) : 0;
                @endphp
                <a href="{{ route('inventaris.project', $project['id']) }}" wire:navigate wire:key="project-{{ $project['id'] }}"
                    class="group rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-red-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-red-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading class="truncate group-hover:text-red-700 dark:group-hover:text-red-400">
                                {{ $project['name'] }}
                            </flux:heading>
                            <flux:text size="sm" class="mt-0.5">{{ $project['company'] }}</flux:text>
                        </div>
                        <flux:badge size="sm" :color="$project['status_color']">{{ $project['status_label'] }}</flux:badge>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ $project['sn_units'] }}/{{ $project['total_units'] }} unit ber-SN</span>
                            <span>{{ $project['spektek_count'] }} spektek</span>
                        </div>
                        <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="bg-emerald-500" style="width: {{ $snPct }}%"></div>
                            <div class="bg-blue-400" style="width: {{ $genOnlyPct }}%"></div>
                        </div>
                    </div>
                    <flux:text size="xs" class="mt-3">Aktivitas terakhir: {{ $project['last_activity'] }}</flux:text>
                </a>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:text>Tidak ada project yang cocok dengan filter "{{ $filter }}".</flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>

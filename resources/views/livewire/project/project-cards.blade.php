<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component
{
    public array $projects = [];
    public array $pagination = [];
    public string $search = '';
    public int $limit = 12;
    public int $currentPage = 1;

    public function mount(): void
    {
        $this->fetchProjects();
    }

    public function fetchProjects(): void
    {
        $response = Http::timeout(120)->retry(3, 200)->get(config('services.api_project') . 'projects/search', [
            'limit' => $this->limit,
            'page'  => $this->currentPage,
            'name'  => $this->search,
        ])->json();

        $this->projects   = $response['data'] ?? [];
        $this->pagination = $response['pagination'] ?? [];
    }

    public function applyFilters(): void
    {
        $this->currentPage = 1;
        $this->fetchProjects();
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = $page;
        $this->fetchProjects();
    }

    public function placeholder(): object
    {
        return view('components.placeholder.ph_project');
    }
}
?>

@php
    $statusConfig = [
        'WAITING'     => ['label' => 'Menunggu',    'bg' => 'bg-red-100 dark:bg-red-900/30',   'text' => 'text-red-700 dark:text-red-400',   'bar' => 'bg-red-500',  'accent' => 'from-red-400 to-red-500'],
        'ON PROGRESS' => ['label' => 'Berjalan',    'bg' => 'bg-blue-100 dark:bg-blue-900/30',     'text' => 'text-blue-700 dark:text-blue-400',     'bar' => 'bg-blue-500',   'accent' => 'from-blue-400 to-blue-600'],
        'DONE'        => ['label' => 'Selesai',     'bg' => 'bg-green-100 dark:bg-green-900/30',   'text' => 'text-green-700 dark:text-green-400',   'bar' => 'bg-green-500',  'accent' => 'from-green-400 to-emerald-500'],
        'CANCELLED'   => ['label' => 'Dibatalkan',  'bg' => 'bg-gray-100 dark:bg-gray-900/30',       'text' => 'text-gray-700 dark:text-gray-400',       'bar' => 'bg-gray-500',    'accent' => 'from-gray-400 to-gray-500'],
        'MAINTENANCE' => ['label' => 'Maintenance', 'bg' => 'bg-purple-100 dark:bg-purple-900/30', 'text' => 'text-purple-700 dark:text-purple-400', 'bar' => 'bg-purple-500', 'accent' => 'from-purple-400 to-violet-500'],
    ];
    $defaultStatus = ['label' => 'Lainnya', 'bg' => 'bg-zinc-100 dark:bg-zinc-800', 'text' => 'text-zinc-500 dark:text-zinc-400', 'bar' => 'bg-zinc-400', 'accent' => 'from-zinc-300 to-zinc-400'];
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading class="text-2xl font-bold">Project</flux:heading>
            <flux:description>
                @if(!empty($pagination['total']))
                    Menampilkan {{ count($projects) }} dari {{ $pagination['total'] }} proyek
                @else
                    Kelola semua proyek Anda
                @endif
            </flux:description>
        </div>
        <flux:button icon="plus-circle" :href="route('projects.create')" wire:navigate variant="primary" class="w-full sm:w-auto shrink-0">
            Tambah Proyek
        </flux:button>
    </div>

    {{-- Search Bar --}}
    <div class="relative">
        <flux:input
            icon="magnifying-glass"
            wire:model="search"
            wire:keydown.enter="applyFilters"
            wire:loading.attr="disabled"
            placeholder="Cari proyek berdasarkan nama..."
            class="w-full"
        />
        <div wire:loading wire:target="applyFilters,goToPage" class="absolute right-3 top-1/2 -translate-y-1/2">
            <svg class="animate-spin h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a10 10 0 00-10 10h4z"/>
            </svg>
        </div>
    </div>

    {{-- Project Grid --}}
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
         wire:loading.remove wire:target="applyFilters,goToPage">

        @forelse($projects as $item)
            @php
                $status     = $statusConfig[$item['status']] ?? $defaultStatus;
                $progress   = (int) ($item['progress'] ?? 0);
                $teamCount  = count($item['support_teams'] ?? []) + count($item['support_team_internals'] ?? []);
                $value      = 'Rp ' . number_format($item['value'] ?? 0, 0, ',', '.');
                $startDate  = $item['start_date'] ? \Carbon\Carbon::parse($item['start_date'])->translatedFormat('d M Y') : '-';
                $endDate    = $item['end_date']   ? \Carbon\Carbon::parse($item['end_date'])->translatedFormat('d M Y')   : '-';
                $leaderName = $item['project_leader_name'] ?? 'Unknown';
                $initials   = collect(explode(' ', $leaderName))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->join('');
            @endphp

            <div wire:key="project-{{ $item['id'] }}"
                 class="group relative flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 overflow-hidden">

                {{-- Status accent bar --}}
                <div class="h-1.5 w-full bg-gradient-to-r {{ $status['accent'] }}"></div>

                <div class="flex flex-col flex-1 p-5 gap-4">

                    {{-- Code + Status badge --}}
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-xs font-mono font-semibold text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2.5 py-1 rounded-md">
                            <flux:icon name="qr-code" class="w-3.5 h-3.5"/>
                            {{ $item['code'] ?? '-' }}
                        </span>
                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $status['bg'] }} {{ $status['text'] }}">
                            {{ $status['label'] }}
                        </span>
                    </div>

                    {{-- Project Name --}}
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 line-clamp-3 leading-snug min-h-[3.75rem]">
                        {{ $item['name'] }}
                    </h3>

                    {{-- Client --}}
                    <div class="flex items-center gap-2">
                        <flux:icon name="building-office-2" class="w-4 h-4 shrink-0 text-zinc-400"/>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400 truncate">{{ $item['client'] ?? '-' }}</span>
                    </div>

                    {{-- Progress --}}
                    <div class="space-y-1.5">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Progress</span>
                            <span class="text-xs font-bold {{ $status['text'] }}">{{ $progress }}%</span>
                        </div>
                        <div class="w-full h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full {{ $status['bar'] }} rounded-full transition-all duration-500"
                                 style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    {{-- Meta --}}
                    <div class="space-y-2 text-xs text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                        <div class="flex items-center gap-2">
                            <flux:icon name="calendar-days" class="w-3.5 h-3.5 shrink-0"/>
                            <span class="truncate">{{ $startDate }} &rarr; {{ $endDate }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon name="banknotes" class="w-3.5 h-3.5 shrink-0"/>
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300 truncate">{{ $value }}</span>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-3 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-br {{ $status['accent'] }} flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                            {{ $initials }}
                        </div>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 truncate">{{ $leaderName }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($teamCount > 0)
                            <span class="inline-flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="users" class="w-3.5 h-3.5"/>
                                {{ $teamCount }}
                            </span>
                        @endif
                        <a href="{{ route('projects.show', $item['id']) }}"
                           class="relative z-10 inline-flex items-center justify-center w-7 h-7 rounded-lg bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:border-zinc-400 transition-colors">
                            <flux:icon name="arrow-right" class="w-3.5 h-3.5"/>
                        </a>
                    </div>
                </div>

                {{-- Full-card clickable overlay --}}
                <a href="{{ route('projects.show', $item['id']) }}" class="absolute inset-0 z-0" aria-hidden="true"></a>
            </div>

        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-24 text-center">
                <div class="w-16 h-16 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-4">
                    <flux:icon name="folder-open" class="w-8 h-8 text-zinc-400"/>
                </div>
                <p class="text-zinc-600 dark:text-zinc-400 font-medium">Tidak ada proyek ditemukan</p>
                <p class="text-zinc-400 dark:text-zinc-500 text-sm mt-1">Coba ubah kata kunci pencarian Anda</p>
            </div>
        @endforelse
    </div>

    {{-- Loading skeleton --}}
    <div wire:loading wire:target="applyFilters,goToPage"
         class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach(range(1, 1) as $_)
            <flux:skeleton.group animate="shimmer"
                class="flex flex-col rounded-2xl border border-zinc-100 dark:border-zinc-800 overflow-hidden">
                <flux:skeleton class="h-1.5 w-full rounded-none"/>
                <div class="p-5 space-y-4">
                    <div class="flex justify-between">
                        <flux:skeleton class="h-6 w-14 rounded-md"/>
                        <flux:skeleton class="h-6 w-20 rounded-full"/>
                    </div>
                    <flux:skeleton class="h-4 w-full rounded"/>
                    <flux:skeleton class="h-4 w-3/4 rounded"/>
                    <flux:skeleton class="h-4 w-1/2 rounded"/>
                    <flux:skeleton class="h-1.5 w-full rounded-full"/>
                    <div class="space-y-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:skeleton class="h-3 w-full rounded"/>
                        <flux:skeleton class="h-3 w-3/4 rounded"/>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <flux:skeleton class="w-6 h-6 rounded-full"/>
                        <flux:skeleton class="h-3 w-28 rounded"/>
                    </div>
                    <flux:skeleton class="h-7 w-7 rounded-lg"/>
                </div>
            </flux:skeleton.group>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if(!empty($pagination) && ($pagination['last_page'] ?? 1) > 1)
        @php
            $lastPage    = $pagination['last_page'];
            $activePage  = $currentPage;
            $pages = collect(range(1, $lastPage))->filter(
                fn($p) => $p === 1 || $p === $lastPage || abs($p - $activePage) <= 2
            )->values();
        @endphp

        <div wire:loading.remove wire:target="applyFilters,goToPage"
             class="flex items-center justify-center gap-1.5 pt-2">

            <flux:button
                wire:click="goToPage({{ max(1, $currentPage - 1) }})"
                :disabled="$currentPage <= 1"
                variant="outline"
                icon="chevron-left"
                size="sm"
            />

            @foreach($pages as $i => $page)
                @if($i > 0 && $page - $pages[$i - 1] > 1)
                    <span class="text-zinc-400 px-1 text-sm">…</span>
                @endif
                <flux:button
                    wire:click="goToPage({{ $page }})"
                    variant="{{ $page === $currentPage ? 'primary' : 'outline' }}"
                    size="sm"
                    class="w-9"
                >
                    {{ $page }}
                </flux:button>
            @endforeach

            <flux:button
                wire:click="goToPage({{ min($lastPage, $currentPage + 1) }})"
                :disabled="$currentPage >= $lastPage"
                variant="outline"
                icon="chevron-right"
                size="sm"
            />
        </div>

        <p wire:loading.remove wire:target="applyFilters,goToPage"
           class="text-center text-xs text-zinc-400 dark:text-zinc-500 pb-2">
            Halaman {{ $currentPage }} dari {{ $lastPage }}
            &middot; Total {{ $pagination['total'] }} proyek
        </p>
    @endif

</div>

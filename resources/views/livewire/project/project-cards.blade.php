<?php

use App\Services\ProjectCache;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Masmerise\Toaster\Toaster;

new class extends Component
{
    public array $projects = [];
    public array $pagination = [];
    public array $availableYears = [];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'year', except: '')]
    public string $year = '';

    #[Url(as: 'view', except: 'cards')]
    public string $viewMode = 'cards';

    public int $limit = 12;
    public int $currentPage = 1;

    public function mount(): void
    {
        $this->fetchProjects();
        $this->buildAvailableYears();
    }

    public function buildAvailableYears(): void
    {
        $currentYear = (int) now()->year;
        $startYear = 2022;

        $this->availableYears = collect(range($currentYear, $startYear))->all();
    }

    public function fetchProjects(): void
    {
        $isDefaultView = $this->search === '' && $this->year === '' && $this->currentPage === 1;

        if ($isDefaultView) {
            $response = app(ProjectCache::class)->defaultProjectsList($this->limit);
        } else {
            $params = [
                'limit' => $this->limit,
                'page' => $this->currentPage,
                'name' => $this->search,
            ];

            if ($this->year !== '') {
                $params['year'] = $this->year;
            }

            $response = Http::timeout(120)->retry(3, 200)->get(config('services.api_project').'projects/search', $params)->json();
        }

        $data = $response['data'] ?? [];

        if ($this->year !== '') {
            $data = array_values(array_filter(
                $data,
                fn ($p) => ! empty($p['start_date']) && (int) \Carbon\Carbon::parse($p['start_date'])->year === (int) $this->year
            ));
        }

        $this->dispatch('fetchProject', $data);

        $this->projects = $data;
        $this->pagination = $response['pagination'] ?? [];
    }

    public function applyFilters(): void
    {
        $this->currentPage = 1;
        $this->fetchProjects();
    }

    public function updatedYear(): void
    {
        $this->applyFilters();
    }

    public function setView(string $mode): void
    {
        $this->viewMode = in_array($mode, ['cards', 'list']) ? $mode : 'cards';
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = $page;
        $this->fetchProjects();
    }

    public function deleteProject(int $id): void
    {
        try {
            $response = Http::delete(config('services.api_project') . 'projects/' . $id);

            if ($response->successful()) {
                app(ProjectCache::class)->flushProjects();

                $this->projects = array_values(array_filter(
                    $this->projects,
                    fn ($p) => (int) ($p['id'] ?? 0) !== $id
                ));

                Toaster::success('Proyek berhasil dihapus');

                if (count($this->projects) === 0 && $this->currentPage > 1) {
                    $this->currentPage--;
                }

                $this->fetchProjects();

                return;
            }

            Toaster::error($response->json('message') ?? 'Gagal menghapus proyek');
        } catch (\Throwable $e) {
            Toaster::error('Gagal menghapus proyek');
            Log::error('Failed to delete project', ['id' => $id, 'error' => $e->getMessage()]);
        }
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
        <flux:button icon="plus-circle" href="{{ route('projects.create') }}" wire:navigate variant="primary" class="w-full sm:w-auto shrink-0">
            Tambah Proyek
        </flux:button>
    </div>

    {{-- Filter Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        {{-- Search Bar --}}
        <div class="relative flex-1">
            <flux:input
                icon="magnifying-glass"
                wire:model="search"
                wire:keydown.enter="applyFilters"
                wire:loading.attr="disabled"
                placeholder="Cari proyek berdasarkan nama..."
                class="w-full"
            />
            <div wire:loading wire:target="applyFilters,goToPage,updatedYear" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                    <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a10 10 0 00-10 10h4z"/>
                </svg>
            </div>
        </div>

        {{-- Year Filter --}}
        <flux:select
            wire:model.live="year"
            placeholder="Semua Tahun"
            class="w-full sm:w-44"
        >
            <flux:select.option value="">Semua Tahun</flux:select.option>
            @foreach($availableYears as $y)
                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
            @endforeach
        </flux:select>

        {{-- View Toggle --}}
        <div class="inline-flex rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-0.5 shrink-0">
            <button type="button"
                    wire:click="setView('cards')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition cursor-pointer {{ $viewMode === 'cards' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">
                <flux:icon name="squares-2x2" class="w-4 h-4"/>
                Cards
            </button>
            <button type="button"
                    wire:click="setView('list')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition cursor-pointer {{ $viewMode === 'list' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">
                <flux:icon name="bars-3" class="w-4 h-4"/>
                List
            </button>
        </div>
    </div>

    {{-- Project Grid (Cards View) --}}
    @if($viewMode === 'cards')
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
         wire:loading.remove wire:target="applyFilters,goToPage,year">

        @forelse($projects as $item)
            @php
                $status     = $statusConfig[$item['status']] ?? $defaultStatus;
                $progress   = (int) ($item['progress'] ?? 0);
                $teamCount  = count($item['support_teams'] ?? []) + count($item['support_team_internals'] ?? []);
                $startDate  = $item['start_date'] ? \Carbon\Carbon::parse($item['start_date'])->translatedFormat('d M Y') : '-';
                $endDate    = $item['end_date']   ? \Carbon\Carbon::parse($item['end_date'])->translatedFormat('d M Y')   : '-';
                $leaderName = $item['project_leader_name'] ?? 'Unknown';
                $initials   = collect(explode(' ', $leaderName))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->join('');
            @endphp

            <div wire:key="project-{{ $item['id'] }}"
                 class="group relative flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 overflow-hidden">

                {{-- Action menu (top-right, above overlay) --}}
                <div class="absolute top-3 right-3 z-20 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                    <flux:dropdown align="end">
                        <flux:button size="xs" variant="filled" icon="ellipsis-horizontal" class="bg-white/90 dark:bg-zinc-800/90 backdrop-blur shadow-sm" />
                        <flux:menu>
                            <flux:menu.item icon="eye" :href="route('projects.show', $item['id'])" wire:navigate>
                                Detail
                            </flux:menu.item>
                            <flux:menu.item icon="pencil-square" :href="route('projects.edit', $item['id'])" wire:navigate>
                                Edit
                            </flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item
                                icon="trash"
                                variant="danger"
                                wire:click="deleteProject({{ $item['id'] }})"
                                wire:confirm="Yakin ingin menghapus proyek &quot;{{ addslashes($item['name'] ?? '') }}&quot;? Tindakan ini tidak bisa dibatalkan."
                            >
                                Hapus Proyek
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

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

    {{-- Loading skeleton (Cards View) --}}
    <div wire:loading wire:target="applyFilters,goToPage,year"
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
    @endif

    {{-- Project Table (List View) --}}
    @if($viewMode === 'list')
    <div wire:loading.remove wire:target="applyFilters,goToPage,year"
         class="overflow-x-auto rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Kode</th>
                    <th class="px-4 py-3 text-left font-semibold">Nama Proyek</th>
                    <th class="px-4 py-3 text-left font-semibold">Klien</th>
                    <th class="px-4 py-3 text-left font-semibold">Status</th>
                    <th class="px-4 py-3 text-left font-semibold">Progress</th>
                    <th class="px-4 py-3 text-left font-semibold">Periode</th>
                    <th class="px-4 py-3 text-left font-semibold">Leader</th>
                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
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

                    <tr wire:key="project-row-{{ $item['id'] }}"
                        class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">

                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 text-xs font-mono font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded-md">
                                {{ $item['code'] ?? '-' }}
                            </span>
                        </td>

                        <td class="px-4 py-3 max-w-xs">
                            <flux:tooltip :content="$item['name']">
                            <a href="{{ route('projects.show', $item['id']) }}"
                               wire:navigate
                               class="font-semibold text-zinc-800 dark:text-zinc-100 hover:underline line-clamp-2">
                                   {{ $item['name'] }}
                                </a>
                            </flux:tooltip>
                            </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                                <flux:icon name="building-office-2" class="w-4 h-4 shrink-0 text-zinc-400"/>
                                <span class="truncate max-w-[160px]">{{ $item['client'] ?? '-' }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $status['bg'] }} {{ $status['text'] }}">
                                {{ $status['label'] }}
                            </span>
                        </td>

                        <td class="px-4 py-3 min-w-[140px]">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full {{ $status['bar'] }} rounded-full transition-all duration-500"
                                         style="width: {{ $progress }}%"></div>
                                </div>
                                <span class="text-xs font-bold {{ $status['text'] }} w-10 text-right">{{ $progress }}%</span>
                            </div>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                            <div class="flex items-center gap-1.5">
                                <flux:icon name="calendar-days" class="w-3.5 h-3.5 shrink-0"/>
                                {{ $startDate }} &rarr; {{ $endDate }}
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-full bg-gradient-to-br {{ $status['accent'] }} flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                                    {{ $initials }}
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-[120px]">{{ $leaderName }}</span>
                                @if($teamCount > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                        <flux:icon name="users" class="w-3.5 h-3.5"/>
                                        {{ $teamCount }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-4 py-3 text-right">
                            <flux:dropdown align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal"/>
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('projects.show', $item['id'])" wire:navigate>
                                        Detail
                                    </flux:menu.item>
                                    <flux:menu.item icon="pencil-square" :href="route('projects.edit', $item['id'])" wire:navigate>
                                        Edit
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        icon="trash"
                                        variant="danger"
                                        wire:click="deleteProject({{ $item['id'] }})"
                                        wire:confirm="Yakin ingin menghapus proyek &quot;{{ addslashes($item['name'] ?? '') }}&quot;? Tindakan ini tidak bisa dibatalkan."
                                    >
                                        Hapus Proyek
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-16 h-16 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-4">
                                    <flux:icon name="folder-open" class="w-8 h-8 text-zinc-400"/>
                                </div>
                                <p class="text-zinc-600 dark:text-zinc-400 font-medium">Tidak ada proyek ditemukan</p>
                                <p class="text-zinc-400 dark:text-zinc-500 text-sm mt-1">Coba ubah filter atau kata kunci pencarian Anda</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Loading skeleton (List View) --}}
    <div wire:loading wire:target="applyFilters,goToPage,year"
         class="overflow-hidden rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <flux:skeleton.group animate="shimmer" class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach(range(1, 6) as $_)
                <div class="flex items-center gap-4 px-4 py-4">
                    <flux:skeleton class="h-5 w-16 rounded"/>
                    <flux:skeleton class="h-5 flex-1 rounded"/>
                    <flux:skeleton class="h-5 w-24 rounded"/>
                    <flux:skeleton class="h-5 w-20 rounded-full"/>
                    <flux:skeleton class="h-5 w-32 rounded"/>
                    <flux:skeleton class="h-5 w-24 rounded"/>
                </div>
            @endforeach
        </flux:skeleton.group>
    </div>
    @endif

    {{-- Pagination --}}
    @if(!empty($pagination) && ($pagination['last_page'] ?? 1) > 1)
        @php
            $lastPage    = $pagination['last_page'];
            $activePage  = $currentPage;
            $pages = collect(range(1, $lastPage))->filter(
                fn($p) => $p === 1 || $p === $lastPage || abs($p - $activePage) <= 2
            )->values();
        @endphp

        <div wire:loading.remove wire:target="applyFilters,goToPage,year"
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

        <p wire:loading.remove wire:target="applyFilters,goToPage,year"
           class="text-center text-xs text-zinc-400 dark:text-zinc-500 pb-2">
            Halaman {{ $currentPage }} dari {{ $lastPage }}
            &middot; Total {{ $pagination['total'] }} proyek
        </p>
    @endif

</div>

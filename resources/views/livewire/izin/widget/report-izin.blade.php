<?php

use App\Services\IzinCache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public string $tab = 'izin';

    // Ringkasan Izin
    public int $izinApproved = 0;

    public int $izinRejected = 0;

    public int $izinTotal = 0;

    public array $izinGroup = [];

    // Ringkasan SPD
    public int $spdApproved = 0;

    public int $spdWaiting = 0;

    public int $spdTotal = 0;

    public array $spdGroup = [];

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.placeholder.ph_report_izin');
    }

    public function mount(): void
    {
        if (! $this->canViewIzin) {
            $this->tab = 'spd';
        }

        $this->loadData();
    }

    /**
     * Hanya departemen IT yang punya akses ke ringkasan izin; selain itu cuma SPD.
     */
    #[Computed]
    public function canViewIzin(): bool
    {
        return (bool) Auth::user()?->isInDepartment('it') && Auth::user()?->hasPermission('izin.create');
    }

    #[On('izinAdded')]
    public function refreshData(): void
    {
        $cache = app(IzinCache::class);
        $cache->flushUser(Auth::user()->username);
        $cache->flushGroup();
        $this->loadData();
    }

    public function setTab(string $tab): void
    {
        if (! $this->canViewIzin) {
            $tab = 'spd';
        }

        $this->tab = in_array($tab, ['izin', 'spd'], true) ? $tab : 'izin';
        $this->dispatchGroup();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->tab === 'izin' ? $this->izinTotal : $this->spdTotal;
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->tab === 'izin'
            ? max(0, $this->izinTotal - $this->izinApproved - $this->izinRejected)
            : max(0, $this->spdTotal - $this->spdApproved - $this->spdWaiting);
    }

    /**
     * @return array<string, float>
     */
    #[Computed]
    public function percentages(): array
    {
        $total = max($this->totalCount, 1);

        if ($this->tab === 'izin') {
            return [
                'approved' => round($this->izinApproved / $total * 100, 1),
                'rejected' => round($this->izinRejected / $total * 100, 1),
                'pending' => round($this->pendingCount / $total * 100, 1),
            ];
        }

        return [
            'approved' => round($this->spdApproved / $total * 100, 1),
            'waiting' => round($this->spdWaiting / $total * 100, 1),
            'pending' => round($this->pendingCount / $total * 100, 1),
        ];
    }

    /**
     * @return array<int, array{label:string,value:int,percent:float,dot:string,text:string}>
     */
    #[Computed]
    public function stats(): array
    {
        if ($this->tab === 'izin') {
            return [
                ['label' => 'Disetujui', 'value' => $this->izinApproved, 'percent' => $this->percentages['approved'], 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-600'],
                ['label' => 'Ditolak', 'value' => $this->izinRejected, 'percent' => $this->percentages['rejected'], 'dot' => 'bg-rose-500', 'text' => 'text-rose-600'],
                ['label' => 'Pending', 'value' => $this->pendingCount, 'percent' => $this->percentages['pending'], 'dot' => 'bg-amber-400', 'text' => 'text-amber-600'],
            ];
        }

        return [
            ['label' => 'Disetujui', 'value' => $this->spdApproved, 'percent' => $this->percentages['approved'], 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-600'],
            ['label' => 'Waiting', 'value' => $this->spdWaiting, 'percent' => $this->percentages['waiting'], 'dot' => 'bg-blue-500', 'text' => 'text-blue-600'],
            ['label' => 'Pending', 'value' => $this->pendingCount, 'percent' => $this->percentages['pending'], 'dot' => 'bg-amber-400', 'text' => 'text-amber-600'],
        ];
    }

    protected function dispatchGroup(): void
    {
        $this->dispatch('widget-pengajuan', data: $this->tab === 'izin' ? $this->izinGroup : $this->spdGroup);
    }

    protected function loadData(): void
    {
        $cache = app(IzinCache::class);
        if ($this->canViewIzin) {
            try {
                $json = $cache->dashboard(Auth::user()->username, Auth::user()->id);
                $this->izinApproved = (int) ($json['data']['approve_izin'] ?? 0);
                $this->izinRejected = (int) ($json['data']['failed_izin'] ?? 0);
                $this->izinTotal = (int) ($json['data']['all_izin'] ?? 0);
                $this->izinGroup = $json['data']['group'] ?? [];
            } catch (\Throwable $e) {
                Log::error('Failed to load izin summary', ['message' => $e->getMessage()]);
            }
        }

        try {
            if (Auth::user()->hasPermission('spd.view.all')) {
                $json = $cache->spdListAll();
                $rows = collect($json['data'] ?? []);
                $this->spdApproved = $rows->where('is_submitted', 1)->where('is_approved', 1)->count();
                $this->spdWaiting = $rows->where('is_submitted', 1)->where('is_approved', 0)->count();
                $this->spdTotal = $rows->count();
            } else {
                $this->spdApproved = (int) ($json['data']['spd_approved'] ?? 0);
                $this->spdWaiting = (int) ($json['data']['spd_waiting'] ?? 0);
                $this->spdTotal = (int) ($json['data']['spd_all'] ?? 0);
            }

            $this->spdGroup = $cache->groupDashboard();
        } catch (\Throwable $e) {
            Log::error('Failed to load spd summary', ['message' => $e->getMessage()]);
        }

        $this->dispatchGroup();
    }
}; ?>

<div>
    <div class="bg-white rounded-2xl border border-zinc-200 p-4 sm:p-6 h-full flex flex-col">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 size-10 rounded-xl bg-red-50 ring-1 ring-red-100 flex items-center justify-center">
                    <flux:icon name="chart-bar" class="size-5 text-red-600" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg" class="text-zinc-900 leading-tight">
                        {{ $tab === 'izin' ? 'Ringkasan Izin' : 'Ringkasan SPD' }}
                    </flux:heading>
                    <flux:description class="text-xs text-zinc-500">
                        {{ $tab === 'izin' ? 'Status pengajuan izin Anda' : 'Status pengajuan SPD Pegawai' }}
                    </flux:description>
                </div>
            </div>
            <flux:badge size="sm" color="zinc" variant="pill" class="shrink-0">All-time</flux:badge>
        </div>

        {{-- Tab toggle (hanya departemen IT yang bisa beralih ke ringkasan izin) --}}
        @if ($this->canViewIzin)
        <div class="mt-5 inline-flex w-full rounded-xl bg-zinc-100 p-1">
            <button type="button" wire:click="setTab('izin')"
                @class([
                    'px-4 w-full py-2 text-sm font-medium rounded-lg transition-all duration-200',
                    'bg-white text-zinc-900 shadow-sm' => $tab === 'izin',
                    'text-zinc-500 hover:text-zinc-700' => $tab !== 'izin',
                ])>
                Izin
            </button>
            <button type="button" wire:click="setTab('spd')"
                @class([
                    'px-4 w-full py-2 text-sm font-medium rounded-lg transition-all duration-200',
                    'bg-white text-zinc-900 shadow-sm' => $tab === 'spd',
                    'text-zinc-500 hover:text-zinc-700' => $tab !== 'spd',
                ])>
                SPD
            </button>
        </div>
        @endif

        {{-- Hero stat --}}
        <div class="mt-6 flex items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-medium text-zinc-500">Total Pengajuan</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-4xl sm:text-5xl font-semibold text-zinc-900 tabular-nums tracking-tight">
                        {{ $this->totalCount }}
                    </span>
                    <span class="text-sm text-zinc-400">{{ $tab === 'izin' ? 'izin' : 'SPD' }}</span>
                </div>
            </div>
            @if ($this->totalCount > 0)
            <div class="text-right space-y-0.5">
                <p class="text-[11px] font-medium text-zinc-500">Tingkat Disetujui</p>
                <p class="text-2xl font-semibold text-emerald-600 tabular-nums">
                    {{ $this->percentages['approved'] }}<span class="text-sm">%</span>
                </p>
            </div>
            @endif
        </div>

        {{-- Stacked progress bar --}}
        <div class="mt-4">
            <div class="h-2.5 w-full rounded-full overflow-hidden bg-zinc-100 flex">
                @if ($this->totalCount > 0)
                <div class="bg-emerald-500 h-full transition-all duration-500" style="width: {{ $this->percentages['approved'] }}%"></div>
                @if ($tab === 'izin')
                <div class="bg-rose-500 h-full transition-all duration-500" style="width: {{ $this->percentages['rejected'] }}%"></div>
                @else
                <div class="bg-blue-500 h-full transition-all duration-500" style="width: {{ $this->percentages['waiting'] }}%"></div>
                @endif
                <div class="bg-amber-400 h-full transition-all duration-500" style="width: {{ $this->percentages['pending'] }}%"></div>
                @endif
            </div>
        </div>

        {{-- Legend / mini stats --}}
        <div class="mt-5 grid grid-cols-3 gap-2 sm:gap-3">
            @foreach ($this->stats as $stat)
            <div class="rounded-xl border border-zinc-200 p-3 transition hover:border-zinc-300 hover:shadow-xs">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full {{ $stat['dot'] }}"></span>
                    <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wide">{{ $stat['label'] }}</span>
                </div>
                <div class="mt-1.5 flex items-baseline justify-between gap-1">
                    <span class="text-xl font-semibold text-zinc-900 tabular-nums">{{ $stat['value'] }}</span>
                    <span class="text-[11px] {{ $stat['text'] }} tabular-nums font-medium">{{ $stat['percent'] }}%</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

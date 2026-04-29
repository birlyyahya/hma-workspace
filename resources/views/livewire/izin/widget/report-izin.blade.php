<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public int $approvedCount = 0;
    public int $rejectedCount = 0;
    public int $totalCount = 0;

    public function mount(): void
    {
        $this->loadData();
    }

    #[On('izinAdded')]
    public function refreshData(): void
    {
        Cache::forget($this->cacheKey());
        $this->loadData();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return max(0, $this->totalCount - $this->approvedCount - $this->rejectedCount);
    }

    /**
     * @return array{approved:float,rejected:float,pending:float}
     */
    #[Computed]
    public function percentages(): array
    {
        $total = max($this->totalCount, 1);

        return [
            'approved' => round($this->approvedCount / $total * 100, 1),
            'rejected' => round($this->rejectedCount / $total * 100, 1),
            'pending' => round($this->pendingCount / $total * 100, 1),
        ];
    }

    protected function loadData(): void
    {
        $data = Cache::remember($this->cacheKey(), now()->addHour(), fn () => $this->fetchFromApi());

        $this->dispatch('widget-pengajuan', data: $data['group'] ?? []);

        $this->approvedCount = (int) ($data['approved'] ?? 0);
        $this->rejectedCount = (int) ($data['rejected'] ?? 0);
        $this->totalCount = (int) ($data['total'] ?? 0);
    }

    /**
     * @return array{approved:int,rejected:int,total:int,group:array}
     */
    protected function fetchFromApi(): array
    {
        $empty = ['approved' => 0, 'rejected' => 0, 'total' => 0, 'group' => []];

        try {
            $response = Http::timeout(120)
                ->retry(2, 200)
                ->get(config('services.api_izin').'/global/izin/dashboard/'.Auth::user()->username);

            if (! $response->successful()) {
                Log::error('Izin Dashboard API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $empty;
            }

            $json = $response->json();

            if (! ($json['success'] ?? false)) {
                return $empty;
            }

            return [
                'approved' => $json['data']['approve_izin'] ?? 0,
                'rejected' => $json['data']['failed_izin'] ?? 0,
                'total' => $json['data']['all_izin'] ?? 0,
                'group' => $json['data']['group'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Izin Dashboard API connection error', [
                'message' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    protected function cacheKey(): string
    {
        return 'izin_widget_'.Auth::user()->username;
    }
}; ?>

<div>
    <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 h-full flex flex-col">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 size-10 rounded-xl bg-red-50 ring-1 ring-red-100 flex items-center justify-center">
                    <flux:icon name="chart-bar" class="size-5 text-red-600" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg" class="text-zinc-900 leading-tight">
                        Ringkasan Izin
                    </flux:heading>
                    <flux:description class="text-xs text-zinc-500">
                        Status pengajuan izin Anda
                    </flux:description>
                </div>
            </div>
            <flux:badge size="sm" color="zinc" variant="pill" class="shrink-0">All-time</flux:badge>
        </div>

        {{-- Hero stat --}}
        <div class="mt-6 flex items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-medium text-zinc-500">Total Pengajuan</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-4xl sm:text-5xl font-semibold text-zinc-900 tabular-nums tracking-tight">
                        {{ $totalCount }}
                    </span>
                    <span class="text-sm text-zinc-400">izin</span>
                </div>
            </div>
            @if ($totalCount > 0)
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
                @if ($totalCount > 0)
                    <div class="bg-emerald-500 h-full transition-all duration-500" style="width: {{ $this->percentages['approved'] }}%"></div>
                    <div class="bg-rose-500 h-full transition-all duration-500" style="width: {{ $this->percentages['rejected'] }}%"></div>
                    <div class="bg-amber-400 h-full transition-all duration-500" style="width: {{ $this->percentages['pending'] }}%"></div>
                @endif
            </div>
        </div>

        {{-- Legend / mini stats --}}
        <div class="mt-5 grid grid-cols-3 gap-3">
            @php
                $stats = [
                    ['label' => 'Disetujui', 'value' => $approvedCount, 'percent' => $this->percentages['approved'], 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-600'],
                    ['label' => 'Ditolak', 'value' => $rejectedCount, 'percent' => $this->percentages['rejected'], 'dot' => 'bg-rose-500', 'text' => 'text-rose-600'],
                    ['label' => 'Pending', 'value' => $this->pendingCount, 'percent' => $this->percentages['pending'], 'dot' => 'bg-amber-400', 'text' => 'text-amber-600'],
                ];
            @endphp

            @foreach ($stats as $stat)
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

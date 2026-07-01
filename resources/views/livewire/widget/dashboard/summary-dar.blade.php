<?php

use App\Services\DarCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array<int, array<string,mixed>>
     */
    #[Computed]
    public function tasks(): array
    {
        return app(DarCache::class)->tasks([
            'user_id' => Auth::id(),
            'limit' => 5,
            'status' => 1,
        ])['data'] ?? [];
    }

    /** @return array{total:int,inprogress:int,late:int} */
    #[Computed]
    public function counts(): array
    {
        $tasks      = $this->tasks;
        $inprogress = count($tasks);
        $late       = 0;

        foreach ($tasks as $task) {
            if (! empty($task['end_date']) && now()->gt(Carbon::parse($task['end_date']))) {
                $late++;
            }
        }

        return [
            'total'      => $inprogress,
            'inprogress' => $inprogress - $late,
            'late'       => $late,
        ];
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs">
    <div class="flex items-start justify-between gap-3 mb-5">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 size-10 rounded-xl bg-blue-50 ring-1 ring-blue-100 flex items-center justify-center">
                <flux:icon name="clipboard-document-list" class="size-5 text-blue-600" />
            </div>
            <div class="min-w-0">
                <flux:heading size="lg" class="text-zinc-900 leading-tight">Daily Activity Report</flux:heading>
                <flux:description class="text-xs text-zinc-500">Aktivitas berjalan & yang terlambat</flux:description>
            </div>
        </div>
        <a href="{{ route('dar') }}" wire:navigate
            class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Lihat semua <flux:icon name="arrow-right" class="size-3.5" />
        </a>
    </div>

    <div class="grid grid-cols-3 gap-2 mb-5">
        @php
            $cells = [
                ['label' => 'Total Aktif', 'value' => $this->counts['total'],      'dot' => 'bg-blue-500'],
                ['label' => 'On Progress', 'value' => $this->counts['inprogress'], 'dot' => 'bg-emerald-500'],
                ['label' => 'Terlambat',   'value' => $this->counts['late'],       'dot' => 'bg-rose-500'],
            ];
        @endphp
        @foreach ($cells as $c)
            <div class="rounded-xl border border-zinc-200 p-3">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full {{ $c['dot'] }}"></span>
                    <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wide">{{ $c['label'] }}</span>
                </div>
                <p class="mt-1 text-xl font-semibold text-zinc-900 tabular-nums">{{ $c['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($this->tasks as $task)
            @php
                $end    = ! empty($task['end_date']) ? Carbon::parse($task['end_date']) : null;
                $isLate = $end && now()->gt($end) && (int) ($task['status'] ?? 0) !== 4;
                $start  = ! empty($task['start_date']) ? Carbon::parse($task['start_date']) : null;
            @endphp

            <a href="{{ isset($task['id']) ? route('dar.dar-show', ['id' => $task['id']]) : '#' }}"
                wire:navigate wire:key="dar-{{ $task['id'] ?? $loop->index }}"
                class="flex items-start justify-between gap-3 rounded-xl border border-zinc-100 p-3 hover:border-zinc-200 hover:bg-zinc-50/60 transition">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-900 truncate">{{ $task['activity'] ?? 'Aktivitas' }}</p>
                    <div class="mt-1 flex items-center flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500">
                        @if ($start)
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="play" class="size-3.5" />
                                {{ $start->translatedFormat('d M') }}
                            </span>
                        @endif
                        @if ($end)
                            <span class="inline-flex items-center gap-1 {{ $isLate ? 'text-rose-600' : '' }}">
                                <flux:icon name="flag" class="size-3.5" />
                                {{ $end->translatedFormat('d M') }}
                            </span>
                        @endif
                    </div>
                </div>
                <flux:badge size="sm" :color="$isLate ? 'rose' : 'blue'" variant="pill" class="shrink-0">
                    {{ $isLate ? 'Terlambat' : 'In Progress' }}
                </flux:badge>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-200 p-6 text-center">
                <div class="mx-auto size-10 rounded-full bg-zinc-100 flex items-center justify-center">
                    <flux:icon name="check-circle" class="size-5 text-zinc-400" />
                </div>
                <p class="mt-2 text-sm text-zinc-500">Tidak ada aktivitas berjalan</p>
            </div>
        @endforelse
    </div>
</div>

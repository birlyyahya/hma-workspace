<?php

use App\Models\User;
use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {

    public $id;

    public function mount() {
        $this->getStagesProperty();
    }

    #[Computed]
    public function getStagesProperty(): array
    {
        return app(ProjectCache::class)->progressStages($this->id);
    }

    #[Computed]
    public function getSummaryProperty(): array
    {
        return app(ProjectCache::class)->progressSummary($this->id);
    }

}; ?>

  @php
    $stages = $this->stages;
    $summary = $this->summary;

    $stageChip = fn (string $status) => match ($status) {
        'done' => ['label' => 'Selesai', 'class' => 'bg-green-50 text-green-700 ring-green-200'],
        'current' => ['label' => 'Berjalan', 'class' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        'upcoming' => ['label' => 'Belum', 'class' => 'bg-zinc-50 text-zinc-700 ring-zinc-200'],
        default => ['label' => 'Pending', 'class' => 'bg-red-50 text-red-500 ring-red-200'],
    };
@endphp

<div>
    <section class="space-y-3">

        {{-- ringkasan progress --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900">Progress Tahapan</h2>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        @if ($summary['current_title'])
                            Sedang berjalan: <span class="font-medium text-blue-600">{{ $summary['current_title'] }}</span>
                        @elseif ($summary['total'] > 0 && $summary['done'] === $summary['total'])
                            Semua tahap telah selesai
                        @else
                            Belum ada tahap yang berjalan
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold leading-none text-zinc-900">{{ $summary['percent'] }}%</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $summary['done'] }} dari {{ $summary['total'] }} tahap</p>
                </div>
            </div>

            {{-- bar progres --}}
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                <div class="h-full rounded-full bg-green-500 transition-all duration-500" style="width: {{ $summary['percent'] }}%"></div>
            </div>

            {{-- rincian per status --}}
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-medium text-green-700 ring-1 ring-green-200">
                    {{ $summary['done'] }} Selesai
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 ring-1 ring-blue-200">
                    {{ $summary['current'] }} Berjalan
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-500 ring-1 ring-red-200">
                    {{ $summary['pending'] }} Pending
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 px-2 py-0.5 text-[10px] font-medium text-zinc-700 ring-1 ring-zinc-200">
                    {{ $summary['upcoming'] }} Belum
                </span>
            </div>
        </div>

        {{-- riwayat tahapan --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6">
            <ol class="relative">
                @foreach ($stages as $stage)
                @php $chip = $stageChip($stage['status']); @endphp
                <li class="relative pl-12 {{ $loop->last ? '' : 'pb-8' }}" x-data="{ open: {{ $stage['status'] === 'current' ? 'true' : 'false' }} }">

                    {{-- garis rail --}}
                    @unless ($loop->last)
                    <span class="absolute left-[17px] top-10 bottom-0 w-0.5 {{ $stage['status'] === 'done' ? 'bg-green-300' : 'bg-zinc-200' }}"></span>
                    @endunless

                    {{-- bullet --}}
                    @if ($stage['status'] === 'done')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-green-500 text-white ring-4 ring-green-100">
                        <flux:icon.check class="w-5 h-5" />
                    </span>
                    @elseif ($stage['status'] === 'current')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-blue-500 text-white ring-4 ring-blue-100">
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5" />
                    </span>
                    @elseif ($stage['status'] === 'pending')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-red-500 text-white ring-4 ring-red-100">
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5" />
                    </span>
                    @else
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-white text-zinc-400 ring-2 ring-zinc-200">
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5" />
                    </span>
                    @endif

                    {{-- header tahap (klik untuk expand) --}}
                    <button type="button" @click="open = !open" class="w-full text-left cursor-pointer group">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold {{ $stage['status'] === 'pending' ? 'text-zinc-400' : 'text-zinc-900' }} group-hover:text-red-600 transition">
                                {{ $stage['title'] }}
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium ring-1 {{ $chip['class'] }}">
                                {{ $chip['label'] }}
                            </span>
                            @foreach ($stage['signals'] as $signal)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-zinc-50 text-zinc-500 ring-1 ring-zinc-200">
                                <flux:icon.bolt class="w-3 h-3" />
                                {{ $signal }}
                            </span>
                            @endforeach
                            <flux:icon.chevron-down class="w-4 h-4 text-zinc-400 ml-auto transition-transform" x-bind:class="open && 'rotate-180'" />
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            <flux:icon.calendar class="w-3.5 h-3.5 inline -mt-0.5" />
                            Rencana: {{ $stage['range'] }}
                            @if ($stage['date'])
                            <span class="text-zinc-300 mx-1">·</span>
                            <span class="text-green-600 font-medium">Selesai {{ $stage['date'] }}</span>
                            @endif
                        </p>
                    </button>

                    {{-- detail expandable --}}
                    <div x-show="open" x-collapse x-cloak class="mt-3 space-y-3">

                        @if (count($stage['activities']))
                        <div class="rounded-xl border border-zinc-200 divide-y divide-zinc-100">
                            <p class="px-3 py-2 text-[11px] font-semibold text-zinc-500 uppercase tracking-wide bg-zinc-50 rounded-t-xl">
                                Aktivitas (dari DAR)
                            </p>
                            @foreach ($stage['activities'] as $activity)
                            <div class="flex items-center gap-3 px-3 py-2.5">
                                <flux:avatar circle size="xs" name="{{ $activity['user'] }}" color="auto" color:seed="{{ $activity['user'] }}" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-zinc-800 truncate">{{ $activity['title'] }}</p>
                                    <p class="text-[11px] text-zinc-400">{{ $activity['user'] }} · {{ $activity['date'] }}</p>
                                </div>
                                <span @class([ 'inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium ring-1' , 'bg-green-50 text-green-700 ring-green-200'=> $activity['status'] === 'CLOSED',
                                    'bg-blue-50 text-blue-700 ring-blue-200' => $activity['status'] === 'OPEN',
                                    'bg-amber-50 text-amber-700 ring-amber-200' => $activity['status'] === 'PENDING',
                                    ])>
                                    {{ $activity['status'] }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        @if (count($stage['documents']))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($stage['documents'] as $doc)
                            <span class="inline-flex items-center gap-1.5 pl-2 pr-3 py-1.5 rounded-lg bg-red-50 text-red-700 ring-1 ring-red-100 text-xs font-medium cursor-pointer hover:bg-red-100 transition">
                                <flux:icon.document-text class="w-4 h-4" />
                                {{ $doc['name'] }}
                                <span class="text-red-400 font-normal">{{ $doc['size'] }}</span>
                            </span>
                            @endforeach
                        </div>
                        @endif

                        @if ($stage['notes'])
                        <div class="flex items-start gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
                            <flux:icon.chat-bubble-bottom-center-text class="w-4 h-4 text-zinc-400 shrink-0 mt-0.5" />
                            {{ $stage['notes'] }}
                        </div>
                        @endif

                        @if (! count($stage['activities']) && ! count($stage['documents']) && ! $stage['notes'])
                        <p class="text-xs text-zinc-400 italic">Belum ada aktivitas maupun dokumen di tahap ini.</p>
                        @endif
                    </div>
                </li>
                @endforeach
            </ol>
        </div>
    </section>
</div>

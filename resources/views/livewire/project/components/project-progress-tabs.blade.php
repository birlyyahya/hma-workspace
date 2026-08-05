<?php

use App\Models\User;
use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $id;


    #[Computed]
    public function stages(): array
    {
        return app(ProjectCache::class)->progressStages($this->id);
    }

    #[Computed]
    public function summary(): array
    {
        return app(ProjectCache::class)->progressSummary($this->id);
    }

    #[On('doc-updated')]
    public function refresh(int $projectId): void
    {
        if ($projectId !== $this->id) {
            return;
        }

        unset($this->stages);
        unset($this->summary);
    }

}; ?>

  @php
    $stages = $this->stages;
    $summary = $this->summary;

    $stageChip = fn (string $status) => match ($status) {
        'done' => ['label' => 'Selesai', 'class' => 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20'],
        'current' => ['label' => 'Berjalan', 'class' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20'],
        'upcoming' => ['label' => 'Belum', 'class' => 'bg-zinc-50 text-zinc-700 ring-zinc-200 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-600'],
        default => ['label' => 'Pending', 'class' => 'bg-red-50 text-red-500 ring-red-200 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20'],
    };

    $barColor = fn (string $status) => match ($status) {
        'done' => 'bg-green-500',
        'current' => 'bg-blue-500',
        'pending' => 'bg-red-500',
        default => 'bg-zinc-200 dark:bg-zinc-700',
    };
@endphp

<div>
    <section class="space-y-3">

        {{-- ringkasan progress --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-5 sm:p-6">
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Progress Timeline</h2>
                <flux:modal.trigger name="progress-guide">
                    <button
                        type="button"
                        title="Cara membaca tahapan progress"
                        aria-label="Cara membaca tahapan progress"
                        class="shrink-0 text-zinc-400 hover:text-red-600 dark:text-zinc-500 dark:hover:text-red-400 transition cursor-pointer"
                    >
                        <flux:icon.question-mark-circle class="w-5 h-5" />
                    </button>
                </flux:modal.trigger>
            </div>
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                @if ($summary['current_title'])
                    Sedang berjalan: <span class="font-medium text-blue-600 dark:text-blue-400">{{ $summary['current_title'] }}</span>
                @elseif ($summary['total'] > 0 && $summary['done'] === $summary['total'])
                    Semua tahap telah selesai
                @else
                    Belum ada tahap yang berjalan
                @endif
            </p>

            <div class="mt-3 flex items-baseline gap-2">
                <p class="text-2xl font-bold leading-none text-zinc-900 dark:text-white">{{ $summary['percent'] }}%</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $summary['done'] }} dari {{ $summary['total'] }} tahap</p>
            </div>

            @if ($summary['total'] > 0)
            {{-- bar progres per-tahap: tiap segmen = satu tahap, urut sesuai jadwal --}}
            <div class="mt-4 flex items-stretch gap-0.5 h-2.5">
                @foreach ($stages as $stage)
                <div
                    class="flex-1 rounded-full {{ $barColor($stage['status']) }} transition-all duration-500"
                    title="{{ $stage['title'] }} — {{ $stageChip($stage['status'])['label'] }}"
                ></div>
                @endforeach
            </div>

            {{-- legenda status --}}
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <span class="inline-flex items-center gap-1.5 text-[11px] text-zinc-600 dark:text-zinc-300">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    {{ $summary['done'] }} Selesai
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-zinc-600 dark:text-zinc-300">
                    <span class="w-2 h-2 rounded-full bg-blue-500 {{ $summary['current'] > 0 ? 'animate-pulse motion-reduce:animate-none' : '' }}"></span>
                    {{ $summary['current'] }} Berjalan
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-zinc-600 dark:text-zinc-300">
                    <span class="w-2 h-2 rounded-full bg-red-500 {{ $summary['pending'] > 0 ? 'animate-pulse motion-reduce:animate-none' : '' }}"></span>
                    {{ $summary['pending'] }} Pending
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-zinc-600 dark:text-zinc-300">
                    <span class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                    {{ $summary['upcoming'] }} Belum
                </span>
            </div>
            @endif
        </div>

        {{-- riwayat tahapan --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-5 sm:p-6">
            @if ($summary['total'] === 0)
            <div class="text-center py-8">
                <flux:icon.map class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mx-auto" />
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">Belum ada tahapan progress</p>
            </div>
            @else
            <ol class="relative">
                @foreach ($stages as $stage)
                @php $chip = $stageChip($stage['status']); @endphp
                <li class="relative pl-12 {{ $loop->last ? '' : 'pb-8' }}" x-data="{ open: {{ $stage['status'] === 'current' ? 'true' : 'false' }} }">

                    {{-- garis rail --}}
                    @unless ($loop->last)
                    <span class="absolute left-[17px] top-10 bottom-0 w-0.5 {{ $stage['status'] === 'done' ? 'bg-green-300 dark:bg-green-700' : 'bg-zinc-200 dark:bg-zinc-700' }}"></span>
                    @endunless

                    {{-- bullet --}}
                    @if ($stage['status'] === 'done')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-green-500 text-white ring-4 ring-green-100 dark:ring-green-500/10">
                        <flux:icon.check class="w-5 h-5" />
                    </span>
                    @elseif ($stage['status'] === 'current')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-blue-500 text-white ring-4 ring-blue-100 dark:ring-blue-500/10">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-60 animate-ping motion-reduce:animate-none"></span>
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5 relative" />
                    </span>
                    @elseif ($stage['status'] === 'pending')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-red-500 text-white ring-4 ring-red-100 dark:ring-red-500/10">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-60 animate-ping motion-reduce:animate-none"></span>
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5 relative" />
                    </span>
                    @else
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-white dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 ring-2 ring-zinc-200 dark:ring-zinc-700">
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5" />
                    </span>
                    @endif

                    {{-- header tahap (klik untuk expand) --}}
                    <button type="button" @click="open = !open" class="w-full text-left cursor-pointer group">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold {{ $stage['status'] === 'pending' ? 'text-zinc-400 dark:text-zinc-500' : 'text-zinc-900 dark:text-white' }} group-hover:text-red-600 dark:group-hover:text-red-400 transition">
                                {{ $stage['title'] }}
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium ring-1 {{ $chip['class'] }}">
                                {{ $chip['label'] }}
                            </span>
                            @foreach ($stage['signals'] as $signal)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 ring-1 ring-zinc-200 dark:ring-zinc-700">
                                <flux:icon.bolt class="w-3 h-3" />
                                {{ $signal }}
                            </span>
                            @endforeach
                            <flux:icon.chevron-down class="w-4 h-4 text-zinc-400 dark:text-zinc-500 ml-auto transition-transform" x-bind:class="open && 'rotate-180'" />
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            <flux:icon.calendar class="w-3.5 h-3.5 inline -mt-0.5" />
                            Rencana: {{ $stage['range'] }}
                            @if ($stage['date'])
                            <span class="text-zinc-300 dark:text-zinc-600 mx-1">·</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">Selesai {{ $stage['date'] }}</span>
                            @endif
                        </p>
                    </button>

                    {{-- detail expandable --}}
                    <div x-show="open" x-collapse x-cloak class="mt-3 space-y-3">

                        @if (count($stage['activities']))
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-800">
                            <p class="px-3 py-2 text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide bg-zinc-50 dark:bg-zinc-800/60 rounded-t-xl">
                                Aktivitas (dari DAR)
                            </p>
                            @foreach ($stage['activities'] as $activity)
                            <div class="flex items-center gap-3 px-3 py-2.5">
                                <flux:avatar circle size="xs" name="{{ $activity['user'] }}" color="auto" color:seed="{{ $activity['user'] }}" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $activity['title'] }}</p>
                                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $activity['user'] }} · {{ $activity['date'] }}</p>
                                </div>
                                <span @class([ 'inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium ring-1' , 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20'=> $activity['status'] === 'CLOSED',
                                    'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20' => $activity['status'] === 'OPEN',
                                    'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20' => $activity['status'] === 'PENDING',
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
                            <span class="inline-flex items-center gap-1.5 pl-2 pr-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 ring-1 ring-red-100 dark:ring-red-500/20 text-xs font-medium cursor-pointer hover:bg-red-100 dark:hover:bg-red-500/20 transition">
                                <flux:icon.document-text class="w-4 h-4" />
                                {{ $doc['name'] }}
                                <span class="text-red-400 dark:text-red-300/60 font-normal">{{ Number::fileSize($doc['size']) }}</span>
                            </span>
                            @endforeach
                        </div>
                        @endif

                        @if ($stage['notes'])
                        <div class="flex items-start gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 px-3 py-2 text-xs text-zinc-600 dark:text-zinc-300">
                            <flux:icon.chat-bubble-bottom-center-text class="w-4 h-4 text-zinc-400 dark:text-zinc-500 shrink-0 mt-0.5" />
                            {{ $stage['notes'] }}
                        </div>
                        @endif

                        @if (! count($stage['activities']) && ! count($stage['documents']) && ! $stage['notes'])
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 italic">Belum ada aktivitas maupun dokumen di tahap ini.</p>
                        @endif
                    </div>
                </li>
                @endforeach
            </ol>
            @endif
        </div>
    </section>

    <flux:modal name="progress-guide" class="w-full max-w-lg max-sm:max-h-[85dvh] overflow-auto">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-red-500 to-red-600 flex items-center justify-center shadow-sm shadow-red-500/20">
                    <flux:icon name="question-mark-circle" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">Cara Membaca Tahapan Progress</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        Warna pada bar dan bulatan tahap di bawah punya arti yang sama.
                    </flux:text>
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Status tahapan</p>

                <div class="flex items-start gap-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2.5">
                    <span class="mt-0.5 w-2.5 h-2.5 rounded-full bg-green-500 shrink-0"></span>
                    <p class="text-xs text-zinc-600 dark:text-zinc-300"><span class="font-medium text-zinc-800 dark:text-zinc-100">Selesai</span> — rentang tanggal sudah lewat dan seluruh task DAR di tahap ini CLOSED, atau (bila tahap tidak punya task DAR) sudah ada minimal 1 dokumen ter-upload.</p>
                </div>
                <div class="flex items-start gap-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2.5">
                    <span class="mt-0.5 w-2.5 h-2.5 rounded-full bg-blue-500 shrink-0"></span>
                    <p class="text-xs text-zinc-600 dark:text-zinc-300"><span class="font-medium text-zinc-800 dark:text-zinc-100">Berjalan</span> — hari ini berada di antara tanggal mulai dan tanggal selesai rencana tahap ini.</p>
                </div>
                <div class="flex items-start gap-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2.5">
                    <span class="mt-0.5 w-2.5 h-2.5 rounded-full bg-red-500 shrink-0"></span>
                    <p class="text-xs text-zinc-600 dark:text-zinc-300"><span class="font-medium text-zinc-800 dark:text-zinc-100">Pending</span> — rentang tanggal sudah lewat tapi belum tuntas: masih ada task DAR yang belum CLOSED, atau tidak ada task DAR maupun dokumen sama sekali.</p>
                </div>
                <div class="flex items-start gap-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2.5">
                    <span class="mt-0.5 w-2.5 h-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600 shrink-0"></span>
                    <p class="text-xs text-zinc-600 dark:text-zinc-300"><span class="font-medium text-zinc-800 dark:text-zinc-100">Belum</span> — tanggal mulai tahap ini belum tiba.</p>
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Isi setiap tahap (klik untuk buka)</p>
                <ul class="space-y-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                    <li class="flex gap-2"><flux:icon.bolt class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500 shrink-0 mt-0.5" /> <span><span class="font-medium text-zinc-800 dark:text-zinc-100">Aktivitas (dari DAR)</span> — task DAR yang kategorinya terhubung ke tahap ini.</span></li>
                    <li class="flex gap-2"><flux:icon.document-text class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500 shrink-0 mt-0.5" /> <span><span class="font-medium text-zinc-800 dark:text-zinc-100">Dokumen</span> — dokumen admin yang cocok dengan judul tahap.</span></li>
                    <li class="flex gap-2"><flux:icon.chat-bubble-bottom-center-text class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500 shrink-0 mt-0.5" /> <span><span class="font-medium text-zinc-800 dark:text-zinc-100">Catatan</span> — catatan opsional dari jadwal BEPM, bila ada.</span></li>
                </ul>
            </div>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2.5">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1">Bagaimana dokumen masuk ke tahap ini?</p>
                <p class="text-xs text-zinc-600 dark:text-zinc-300">Dokumen dicocokkan otomatis berdasarkan kata dari judul tahap (kata ≥4 huruf) yang ditemukan pada judul atau kata kunci dokumen — misalnya kata "kontrak" pada tahap "Tanda Tangan Kontrak". Isi aktivitas DAR sengaja tidak dipakai sebagai kata kunci, supaya dokumen tidak salah masuk ke tahap lain hanya karena kebetulan menyebut nama project yang sama.</p>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>

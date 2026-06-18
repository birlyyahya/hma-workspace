<?php

use App\Models\User;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $project;
    public $spectech;
    public $documents;
    public $loadingDocuments = false;
    public $loadingSpectech = false;

    public function mount(): void
    {
        $this->spectech = app(ProjectCache::class)->spectechFor((int) ($this->project['id'] ?? 0));
    }

    public function getUserProperty()
    {
        return User::find($this->project['project_leader_id']);
    }

    #[Computed]
    public function statusColor(): array
    {
        return match ($this->project['status'] ?? null) {
            'ON PROGRESS' => ['badge' => 'blue', 'dot' => 'bg-blue-500', 'ring' => 'ring-blue-500/20', 'text' => 'text-blue-700', 'bg' => 'bg-blue-50'],
            'WAITING' => ['badge' => 'yellow', 'dot' => 'bg-amber-500', 'ring' => 'ring-amber-500/20', 'text' => 'text-amber-700', 'bg' => 'bg-amber-50'],
            'CLOSED' => ['badge' => 'red', 'dot' => 'bg-red-500', 'ring' => 'ring-red-500/20', 'text' => 'text-red-700', 'bg' => 'bg-red-50'],
            default => ['badge' => 'gray', 'dot' => 'bg-zinc-400', 'ring' => 'ring-zinc-500/20', 'text' => 'text-zinc-700', 'bg' => 'bg-zinc-50'],
        };
    }

    #[Computed]
    public function durationInfo(): array
    {
        $start = Carbon::parse($this->project['start_date']);
        $end = Carbon::parse($this->project['end_date']);
        $now = Carbon::now();

        $totalDays = $start->diffInDays($end) ?: 1;
        $elapsed = max(0, min($totalDays, $start->diffInDays($now->lt($start) ? $start : ($now->gt($end) ? $end : $now))));
        $remaining = $now->lt($end) ? $now->diffInDays($end) : 0;

        return [
            'total' => (int) $totalDays,
            'elapsed' => (int) $elapsed,
            'remaining' => (int) $remaining,
            'percent' => (int) round(($elapsed / $totalDays) * 100),
            'is_overdue' => $now->gt($end),
        ];
    }

    #[Computed]
    public function spectechReceivedValue(): float
    {
        return collect($this->spectech ?? [])->sum(
            fn ($item) => (float) ($item['qty_nominal'] ?? 0) * (float) ($item['qty_recived'] ?? 0)
        );
    }

    public function shareLinkCopied(): void
    {
        Toaster::success('Link share project berhasil disalin');
    }
}

?>

<div>
    <div class="bg-zinc-50/50 min-h-screen">
        <div class="grid grid-cols-12 gap-6">

            {{-- ============ LEFT COLUMN ============ --}}
            <div class="col-span-12 lg:col-span-8 space-y-6">

                {{-- HERO HEADER --}}
                <div class="bg-white rounded-2xl border border-zinc-200 overflow-hidden">
                    <div class="bg-linear-to-br from-red-600 to-red-700 px-8 py-6 text-white">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-white/15 ring-1 ring-white/20 backdrop-blur">
                                        {{ $project['code'] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/15 ring-1 ring-white/20 backdrop-blur">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $this->statusColor['dot'] }} animate-pulse"></span>
                                        {{ $project['status'] }}
                                    </span>
                                </div>
                                <h1 class="mt-2 text-xl font-semibold leading-snug line-clamp-2">
                                    {{ $project['name'] }}
                                </h1>
                                <p class="mt-1 text-sm text-white/80">
                                    {{ $project['company_name'] }}
                                </p>
                            </div>

                            <div
                                class="flex gap-2 shrink-0"
                                x-data="{
                                    copied: false,
                                    shareUrl: @js(route('projects.preview', $project['id'])),
                                    copy() {
                                        const url = this.shareUrl;
                                        const finish = (ok) => {
                                            if (ok) {
                                                this.copied = true;
                                                $wire.shareLinkCopied();
                                                setTimeout(() => this.copied = false, 2000);
                                            } else {
                                                alert('Gagal menyalin link. Salin manual: ' + url);
                                            }
                                        };
                                        if (navigator.clipboard && window.isSecureContext) {
                                            navigator.clipboard.writeText(url).then(() => finish(true)).catch(() => finish(false));
                                            return;
                                        }
                                        try {
                                            const ta = document.createElement('textarea');
                                            ta.value = url;
                                            ta.setAttribute('readonly', '');
                                            ta.style.position = 'fixed';
                                            ta.style.opacity = '0';
                                            document.body.appendChild(ta);
                                            ta.select();
                                            const ok = document.execCommand('copy');
                                            document.body.removeChild(ta);
                                            finish(ok);
                                        } catch (e) {
                                            finish(false);
                                        }
                                    }
                                }"
                            >
                                <button
                                    x-on:click="copy()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white/15 hover:bg-white/25 ring-1 ring-white/20 backdrop-blur transition"
                                    :class="copied ? 'bg-emerald-500/30' : ''"
                                >
                                    <template x-if="!copied">
                                        <span class="flex items-center gap-1.5">
                                            <flux:icon.link class="w-3.5 h-3.5" />
                                            Share
                                        </span>
                                    </template>
                                    <template x-if="copied">
                                        <span class="flex items-center gap-1.5">
                                            <flux:icon.check class="w-3.5 h-3.5" />
                                            Tersalin
                                        </span>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- QUICK STATS GRID --}}
                    <div class="grid grid-cols-2 md:grid-cols-3 divide-x divide-zinc-200">
                        <div class="px-5 py-4">
                            <p class="text-[11px] uppercase tracking-wide text-zinc-500 font-medium">Progress</p>
                            <div class="mt-1 flex items-center gap-2">
                                <p class="text-base font-bold text-red-600">{{ $project['progress'] }}%</p>
                                @if($project['progress'] >= 100)
                                    <flux:icon.check-badge class="w-4 h-4 text-emerald-600" />
                                @endif
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[11px] uppercase tracking-wide text-zinc-500 font-medium">Durasi</p>
                            <p class="mt-1 text-base font-bold text-zinc-900">
                                {{ $this->durationInfo['total'] }}
                                <span class="text-xs font-normal text-zinc-500">hari</span>
                            </p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[11px] uppercase tracking-wide text-zinc-500 font-medium">
                                @if($this->durationInfo['is_overdue'])
                                    Terlewat
                                @else
                                    Sisa Waktu
                                @endif
                            </p>
                            <p class="mt-1 text-base font-bold {{ $this->durationInfo['is_overdue'] ? 'text-red-600' : 'text-zinc-900' }}">
                                {{ $this->durationInfo['remaining'] }}
                                <span class="text-xs font-normal text-zinc-500">hari</span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- TIMELINE PROGRESS --}}
                <div class="bg-white rounded-2xl border border-zinc-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <flux:heading size="sm" class="font-semibold text-zinc-900">Timeline Proyek</flux:heading>
                            <flux:text class="text-xs text-zinc-500 mt-0.5">
                                {{ $this->durationInfo['elapsed'] }} dari {{ $this->durationInfo['total'] }} hari berjalan ({{ $this->durationInfo['percent'] }}%)
                            </flux:text>
                        </div>
                        @if($this->durationInfo['is_overdue'])
                            <flux:badge color="red" size="sm">Overdue</flux:badge>
                        @elseif($this->durationInfo['percent'] >= 80)
                            <flux:badge color="yellow" size="sm">Mendekati Deadline</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">On Track</flux:badge>
                        @endif
                    </div>

                    <div class="relative">
                        <div class="w-full h-2 bg-zinc-100 rounded-full overflow-hidden">
                            <div class="h-full bg-linear-to-r from-red-500 to-red-600 rounded-full transition-all"
                                 style="width: {{ min($this->durationInfo['percent'], 100) }}%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mt-5 pt-5 border-t border-zinc-100">
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-zinc-500">Mulai</p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900">
                                {{ Carbon::parse($project['start_date'])->locale('id')->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-zinc-500">Selesai</p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900">
                                {{ Carbon::parse($project['end_date'])->locale('id')->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        @if(!empty($project['maintenance_date']))
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-zinc-500">Maintenance</p>
                                <p class="mt-1 text-sm font-semibold text-zinc-900">
                                    {{ Carbon::parse($project['maintenance_date'])->locale('id')->translatedFormat('d M Y') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- COMPANY INFO --}}
                <div class="bg-white rounded-2xl border border-zinc-200 p-6">
                    <flux:heading size="sm" class="font-semibold text-zinc-900 mb-4">Informasi Perusahaan</flux:heading>

                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center ring-1 ring-red-100">
                            <flux:icon.building-office-2 class="w-6 h-6 text-red-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-zinc-900">{{ $project['company_name'] }}</p>
                            <p class="text-sm text-zinc-500 mt-1 leading-relaxed">{{ $project['company_address'] }}</p>
                        </div>
                    </div>

                    <div class="mt-5 pt-5 border-t border-zinc-100 flex items-center gap-3">
                        <flux:avatar name="{{ $project['company_director_name'] }}" size="sm" color="red" circle />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-zinc-500">Direktur</p>
                            <p class="text-sm font-medium text-zinc-900 truncate">{{ $project['company_director_name'] }}</p>
                        </div>
                        @if(!empty($project['company_director_phone']))
                            <a href="tel:{{ $project['company_director_phone'] }}"
                               class="text-xs text-red-600 hover:text-red-700 font-medium inline-flex items-center gap-1">
                                <flux:icon.phone class="w-3.5 h-3.5" />
                                Hubungi
                            </a>
                        @endif
                    </div>
                </div>

                {{-- SPECTECH SUMMARY --}}
                <div class="bg-white rounded-2xl border border-zinc-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <flux:heading size="sm" class="font-semibold text-zinc-900">
                                Ringkasan Spectech
                            </flux:heading>
                            <flux:text class="text-xs text-zinc-500 mt-0.5">
                                {{ count($this->spectech ?? []) }} item · Rp {{ number_format($this->spectechReceivedValue, 0, ',', '.') }} diterima
                            </flux:text>
                        </div>
                        <span class="text-sm font-semibold text-red-600">{{ $project['progress'] }}%</span>
                    </div>

                    <div class="w-full h-2 bg-zinc-100 rounded-full overflow-hidden mb-5">
                        <div class="h-full bg-linear-to-r from-red-500 to-red-600 rounded-full transition-all"
                             style="width: {{ $project['progress'] }}%"></div>
                    </div>

                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1 -mr-1">
                        @forelse($this->spectech as $item)
                            @php
                                $itemPercentage = (float) ($item['percentage'] ?? 0);
                                $itemComplete = $itemPercentage >= 100;
                            @endphp
                            <div class="group p-3 rounded-lg border border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50/50 transition">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-zinc-900 truncate">
                                            {{ $item['name'] }}
                                        </p>
                                        <p class="text-xs text-zinc-500 mt-0.5">
                                            {{ $item['qty_recived'] ?? 0 }}/{{ $item['qty_total'] ?? 0 }} unit
                                            <span class="mx-1.5 text-zinc-300">•</span>
                                            Rp {{ number_format($item['total_nominal'] ?? 0, 0, ',', '.') }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-2">
                                        <span class="text-xs font-semibold {{ $itemComplete ? 'text-emerald-600' : 'text-zinc-700' }}">
                                            {{ number_format($itemPercentage, 0) }}%
                                        </span>
                                        @if($itemComplete)
                                            <flux:icon.check-circle class="w-4 h-4 text-emerald-600" />
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 w-full h-1 bg-zinc-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $itemComplete ? 'bg-emerald-500' : 'bg-red-500' }} rounded-full"
                                         style="width: {{ min($itemPercentage, 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <flux:icon.cube class="w-8 h-8 text-zinc-300 mx-auto" />
                                <p class="text-sm text-zinc-500 mt-2">Belum ada spectech</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ============ RIGHT COLUMN ============ --}}
            <div class="col-span-12 lg:col-span-4 space-y-6">

                {{-- PROJECT LEADER --}}
                <div class="bg-white rounded-2xl border border-zinc-200 p-6 space-y-3">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm" class="font-semibold text-zinc-900">Project Leader</flux:heading>
                    </div>

                    @if($this->user)
                        <div class="flex items-center gap-3">
                            <flux:avatar name="{{ $this->user->name }}" size="md" color="red" circle />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-zinc-900 truncate">{{ $this->user->name }}</p>
                                <p class="text-xs text-zinc-500 truncate">{{ $this->user->role->name ?? 'Project Lead' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">Belum ditugaskan</p>
                    @endif
                    <flux:separator class="my-4" />
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm" class="font-semibold text-zinc-900">PPK</flux:heading>
                    </div>
                    @if($this->project['ppk'])
                        <div class="flex items-center gap-3">
                            <flux:avatar name="{{ $this->project['ppk'] }}" size="md" color="green" circle />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-zinc-900 truncate">{{ $this->project['ppk'] }}</p>
                                <p class="text-xs text-zinc-500 truncate">Pejabat Pembuat Keputusan</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">Belum ditugaskan</p>
                    @endif
                </div>

                {{-- TEAM SUPPORT --}}
                <div class="bg-white rounded-2xl border border-zinc-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <flux:heading size="sm" class="font-semibold text-zinc-900">Tim Pendukung</flux:heading>
                            <flux:text class="text-xs text-zinc-500 mt-0.5">
                                {{ count($this->project['support_teams'] ?? []) + count($this->project['support_team_internals'] ?? []) }} anggota
                            </flux:text>
                        </div>
                    </div>

                    {{-- External (PPK) --}}
                    @if(!empty($this->project['support_teams']))
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                <p class="text-[11px] uppercase tracking-wide font-medium text-zinc-500">
                                    Tim PPK ({{ count($this->project['support_teams']) }})
                                </p>
                            </div>
                            <div class="space-y-3 max-h-60 overflow-y-auto pr-1 -mr-1">
                                @foreach ($this->project['support_teams'] as $support)
                                    <div class="flex items-center gap-3">
                                        <flux:avatar name="{{ $support }}" size="xs" color="auto" circle color:seed="{{ $support }}" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-zinc-900 truncate">{{ $support }}</p>
                                            <p class="text-xs text-zinc-500">Tim Pendukung PPK</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Internal --}}
                    @if(!empty($this->project['support_team_internals']))
                        @if(!empty($this->project['support_teams']))
                            <flux:separator class="my-4" />
                        @endif
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                <p class="text-[11px] uppercase tracking-wide font-medium text-zinc-500">
                                    Tim Internal ({{ count($this->project['support_team_internals']) }})
                                </p>
                            </div>
                            <div class="space-y-3">
                                @foreach ($this->project['support_team_internals'] as $support)
                                    <div class="flex items-center gap-3">
                                        <flux:avatar name="{{ $support['user_name'] }}" color="auto" size="xs" circle color:seed="{{ $support['id'] }}" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-zinc-900 truncate">{{ $support['user_name'] }}</p>
                                            <p class="text-xs text-zinc-500 truncate">@ {{ $support['user_username'] }}</p>
                                        </div>
                                        @if(!empty($support['user_is_process']))
                                            <flux:badge color="green" size="sm">Active</flux:badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(empty($this->project['support_teams']) && empty($this->project['support_team_internals']))
                        <div class="text-center py-6">
                            <flux:icon.users class="w-8 h-8 text-zinc-300 mx-auto" />
                            <p class="text-sm text-zinc-500 mt-2">Belum ada tim</p>
                        </div>
                    @endif
                </div>

                {{-- METADATA --}}
                <div class="bg-white rounded-2xl border border-zinc-200 p-6">
                    <flux:heading size="sm" class="font-semibold text-zinc-900 mb-4">Metadata</flux:heading>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500 text-xs">Dibuat</dt>
                            <dd class="text-zinc-900 font-medium">
                                {{ Carbon::parse($project['created_at'])->locale('id')->translatedFormat('d M Y') }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500 text-xs">Diperbarui</dt>
                            <dd class="text-zinc-900 font-medium">
                                {{ Carbon::parse($project['updated_at'])->locale('id')->diffForHumans() }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500 text-xs">Project ID</dt>
                            <dd class="text-zinc-900 font-mono text-xs">#{{ $project['id'] }}</dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>
    </div>
</div>

<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $activeTab = null;
    public array $data = [];
    public bool $ready = false;

    /**
     * @var array<string, array{label:string,icon:string,tone:string}>
     */
    protected array $categories = [
        'Tugas luar kantor' => ['label' => 'Tugas Luar Kantor', 'icon' => 'briefcase', 'tone' => 'blue'],
        'Sakit' => ['label' => 'Sakit', 'icon' => 'heart', 'tone' => 'rose'],
        'Dinas luar kota' => ['label' => 'Dinas Luar Kota', 'icon' => 'map-pin', 'tone' => 'violet'],
        'Datang terlambat' => ['label' => 'Datang Terlambat', 'icon' => 'clock', 'tone' => 'amber'],
        'Pulang lebih awal' => ['label' => 'Pulang Lebih Awal', 'icon' => 'arrow-uturn-left', 'tone' => 'emerald'],
        'Lain-lain' => ['label' => 'Lain-lain', 'icon' => 'ellipsis-horizontal', 'tone' => 'zinc'],
    ];

    public function mount(): void
    {
        $cached = Cache::get('izin_widget_group_global');
        $this->setData($cached['group'] ?? []);
    }

    #[On('widget-pengajuan')]
    public function widgetPengajuan(array $data): void
    {
        $this->setData($data);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * @return array<int, array{key:string,label:string,icon:string,tone:string,count:int}>
     */
    #[Computed]
    public function tabs(): array
    {
        $tabs = [];
        foreach ($this->categories as $key => $meta) {
            if (! isset($this->data[$key])) {
                continue;
            }
            $tabs[] = [
                'key' => $key,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'count' => count($this->data[$key]),
            ];
        }

        return $tabs;
    }

    #[Computed]
    public function activeTone(): string
    {
        return $this->categories[$this->activeTab]['tone'] ?? 'red';
    }

    protected function setData(array $group): void
    {
        $this->data = collect($group)
            ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => $v])
            ->toArray();

        $this->activeTab = array_key_first($this->data);
        $this->ready = ! empty($this->data);
    }
}; ?>

@php
    $tonePalette = [
        'red' => ['bg' => 'bg-red-50', 'ring' => 'ring-red-100', 'icon' => 'text-red-600', 'border' => 'border-red-300', 'accent' => 'bg-red-500'],
        'rose' => ['bg' => 'bg-rose-50', 'ring' => 'ring-rose-100', 'icon' => 'text-rose-600', 'border' => 'border-rose-300', 'accent' => 'bg-rose-500'],
        'blue' => ['bg' => 'bg-blue-50', 'ring' => 'ring-blue-100', 'icon' => 'text-blue-600', 'border' => 'border-blue-300', 'accent' => 'bg-blue-500'],
        'violet' => ['bg' => 'bg-violet-50', 'ring' => 'ring-violet-100', 'icon' => 'text-violet-600', 'border' => 'border-violet-300', 'accent' => 'bg-violet-500'],
        'amber' => ['bg' => 'bg-amber-50', 'ring' => 'ring-amber-100', 'icon' => 'text-amber-600', 'border' => 'border-amber-300', 'accent' => 'bg-amber-500'],
        'emerald' => ['bg' => 'bg-emerald-50', 'ring' => 'ring-emerald-100', 'icon' => 'text-emerald-600', 'border' => 'border-emerald-300', 'accent' => 'bg-emerald-500'],
        'zinc' => ['bg' => 'bg-zinc-100', 'ring' => 'ring-zinc-200', 'icon' => 'text-zinc-600', 'border' => 'border-zinc-300', 'accent' => 'bg-zinc-500'],
    ];
@endphp

<div>
    <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 h-full md:max-h-92 flex flex-col">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 size-10 rounded-xl bg-red-50 ring-1 ring-red-100 flex items-center justify-center">
                    <flux:icon name="users" class="size-5 text-red-600" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg" class="text-zinc-900 leading-tight">
                        Pengajuan per Kategori
                    </flux:heading>
                    <flux:description class="text-xs text-zinc-500">
                        Daftar pengajuan dikelompokkan berdasarkan jenis izin
                    </flux:description>
                </div>
            </div>
            <flux:tooltip content="Hanya pengajuan terbaru yang ditampilkan" placement="top">
                <flux:icon name="information-circle" variant="outline" class="size-5 text-zinc-400" />
            </flux:tooltip>
        </div>

        {{-- Pill tabs --}}
        <div class="mt-5 -mx-1 px-1 overflow-x-auto">
            <div class="flex gap-1.5 min-w-max">
                @forelse ($this->tabs as $tab)
                    @php
                        $isActive = $this->activeTab === $tab['key'];
                        $t = $tonePalette[$tab['tone']] ?? $tonePalette['red'];
                    @endphp
                    <button
                        type="button"
                        wire:click="setTab('{{ $tab['key'] }}')"
                        @class([
                            'group inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-1.5 rounded-full text-xs font-medium border transition cursor-pointer',
                            $t['bg'].' '.$t['border'].' '.$t['icon'] => $isActive,
                            'border-zinc-200 text-zinc-600 hover:bg-zinc-50 hover:border-zinc-300' => ! $isActive,
                        ])
                    >
                        <flux:icon name="{{ $tab['icon'] }}" class="size-3.5" />
                        <span>{{ $tab['label'] }}</span>
                        <span @class([
                            'inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full text-[10px] font-semibold tabular-nums',
                            $t['accent'].' text-white' => $isActive,
                            'bg-zinc-200 text-zinc-700' => ! $isActive,
                        ])>{{ $tab['count'] }}</span>
                    </button>
                @empty
                    <div class="text-xs text-zinc-400 py-2">Belum ada kategori pengajuan</div>
                @endforelse
            </div>
        </div>

        {{-- Content --}}
        @php($activeT = $tonePalette[$this->activeTone] ?? $tonePalette['red'])
        <div class="mt-4 space-y-2 flex-1 max-h-74 overflow-y-auto pr-1">
            @forelse ($this->data[$this->activeTab] ?? [] as $item)
                <div class="group relative rounded-xl border border-zinc-200 bg-white p-3 pl-4 transition hover:border-zinc-300 hover:shadow-xs">
                    <span class="absolute left-0 top-3 bottom-3 w-1 rounded-r-full {{ $activeT['accent'] }}"></span>
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:avatar size="xs" name="{{ $item['username'] ?? '?' }}" />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-zinc-900 truncate leading-tight">{{ $item['username'] ?? '-' }}</p>
                                @if (! empty($item['description']))
                                    <p class="text-xs text-zinc-500 line-clamp-1 leading-snug">{{ $item['description'] }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="inline-flex items-center gap-1 shrink-0 rounded-md bg-zinc-50 ring-1 ring-zinc-200 px-2 py-1 text-[10.5px] text-zinc-600 tabular-nums">
                            <flux:icon name="calendar" class="size-3 text-zinc-400" />
                            <span>{{ \Carbon\Carbon::parse($item['start_date'])->format('d M') }} {{ substr($item['start_time'] ?? '', 0, 5) }}</span>
                            <flux:icon name="arrow-right" class="size-3 text-zinc-300" />
                            <span>{{ \Carbon\Carbon::parse($item['end_date'])->format('d M') }} {{ substr($item['end_time'] ?? '', 0, 5) }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="size-12 rounded-full bg-zinc-100 flex items-center justify-center">
                        <flux:icon name="inbox" class="size-6 text-zinc-400" />
                    </div>
                    <p class="mt-2 text-sm font-medium text-zinc-500">Tidak ada pengajuan</p>
                    <p class="text-xs text-zinc-400">Belum ada data untuk kategori ini</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

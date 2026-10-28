<?php

use App\Services\IzinCache;
use App\Services\IzinPdfComposer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public ?array $data = null;

    public int $page = 1;
    public int $perPage = 10;
    public string $search = '';
    public string $sort = 'desc';
    public string $status = '';
    public string $start_date = '';
    public string $end_date = '';

    /** Mode laporan (untuk halaman laporan pengajuan, listing seluruh user). */
    public bool $laporan = false;

    public function mount(): void
    {
        $this->fetchData();
    }

    public function goToPage(int $page): void
    {
        $this->page = $page;
        $this->fetchData();
    }

    #[On('izinSearchUpdated')]
    public function onSearchUpdated($value): void
    {
        $this->search = (string) $value;
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedSort(): void
    {
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedStatus(): void
    {
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedStartDate(): void
    {
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedEndDate(): void
    {
        $this->page = 1;
        $this->fetchData();
    }

    public function resetFilters(): void
    {
        $this->reset('sort', 'status', 'start_date', 'end_date');
        $this->page = 1;
        $this->fetchData();
    }

    #[On('izinAdded')]
    public function fetchData(): void
    {
        $this->data = app(IzinCache::class)->list($this->buildQuery());
        $this->cacheExportRows();
    }

    public function generatePDF(int $id)
    {
        $response = app(IzinCache::class)->detail((int) $id);

        if (($response['success'] ?? false) !== true) {
            Toaster::error('Gagal generate PDF. Data izin tidak ditemukan.');

            return;
        }

        $izin = $response['data'];
        $pdfBase64 = app(IzinPdfComposer::class)->render($izin);

        if ($pdfBase64 === null) {
            Toaster::error('Data pemohon tidak ditemukan.');

            return;
        }

        $filename = 'Pengajuan Izin '.Carbon::parse($izin['start_date'])->format('d M Y').'.pdf';

        return $this->streamPdf($pdfBase64, $filename);
    }

    public function getExportGroups(): array
    {
        try {
            return $this->buildExportGroups();
        } catch (\Throwable $exception) {
            report($exception);
            Toaster::error('Gagal mengambil data untuk export.');

            return [];
        }
    }

    public function exportGroupedPdf()
    {
        try {
            $groups = $this->buildExportGroups();

            if ($groups === []) {
                Toaster::info('Tidak ada data izin untuk diexport.');

                return;
            }

            return Pdf::loadView('pdf.izin-export', ['groups' => $groups])
                ->setPaper('a4', 'landscape')
                ->download('laporan-izin-'.now()->format('Ymd-His').'.pdf');
        } catch (\Throwable $exception) {
            report($exception);
            Toaster::error('Gagal membuat PDF laporan izin.');
        }
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_izin_table');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildQuery(): array
    {
        $base = [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'sort_order' => $this->sort,
        ];

        if ($this->laporan) {
            return $base + ['search_name' => $this->search];
        }



        return $base + [
            'username' => Auth::user()->username,
            'search_alasan' => $this->search,
        ];
    }

    /**
     * @return list<array{label: string, rows: list<array{tanggal: string, nama: string, alasan: string, catatan: string}>}>
     */
    protected function buildExportGroups(): array
    {
        $this->authorize('izin.view.all');

        $groupedRows = [];
        $rows = Cache::tags(['izin'])->get($this->exportRowsCacheKey(), $this->data['data'] ?? []);
        $startDate = $this->start_date !== '' ? Carbon::parse($this->start_date)->startOfDay() : null;
        $endDate = $this->end_date !== '' ? Carbon::parse($this->end_date)->endOfDay() : null;

        foreach ($rows as $izin) {
            $date = Carbon::parse($izin['start_date'] ?? now());

            if (($startDate !== null && $date->lt($startDate)) || ($endDate !== null && $date->gt($endDate))) {
                continue;
            }

            $groupedRows[$date->format('Y-m')][] = [
                'tanggal' => $date->format('d-m-Y'),
                'nama' => (string) ($izin['user_name'] ?? 'N/A'),
                'alasan' => (string) ($izin['reason'] ?? ''),
                'catatan' => (string) ($izin['description'] ?? ''),
            ];
        }
        if ($this->sort === 'asc') {
            ksort($groupedRows);
        } else {
            krsort($groupedRows);
        }

        $groups = [];
        foreach ($groupedRows as $yearMonth => $rows) {
            $groups[] = [
                'label' => Carbon::createFromFormat('!Y-m', $yearMonth)->locale('id')->translatedFormat('F Y'),
                'rows' => $rows,
            ];
        }


        return $groups;
    }

    protected function cacheExportRows(): void
    {
        $cache = Cache::tags(['izin']);
        $cacheKey = $this->exportRowsCacheKey();

        if ($cache->has($cacheKey)) {
            return;
        }

        $query = $this->buildQuery();
        $query['page'] = 1;
        $query['per_page'] = 100;

        $rows = [];
        $page = 1;
        $lastPage = 1;

        do {
            $query['page'] = $page;
            $response = app(IzinCache::class)->list($query);
            $rows = [...$rows, ...($response['data'] ?? [])];
            $lastPage = max(1, (int) ($response['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage);

        $cache->put($cacheKey, $rows, now()->addMinutes(5));
    }

    protected function exportRowsCacheKey(): string
    {
        $query = $this->buildQuery();
        unset($query['page'], $query['per_page']);

        return 'izin:export:'.md5((string) json_encode($query));
    }

    protected function streamPdf(string $pdfBase64, string $filename)
    {
        $pdfBinary = base64_decode(str_replace('data:application/pdf;base64,', '', $pdfBase64));

        return response()->streamDownload(fn () => print ($pdfBinary), $filename);
    }

}; ?>

<div>
    <div class="bg-white relative rounded-2xl border border-zinc-200 overflow-hidden">
        {{-- Loading overlay --}}
        <div
            wire:loading.flex
            wire:target.except="generatePDF"
            class="absolute inset-0 z-20 flex items-center justify-center bg-white/60 backdrop-blur-sm"
        >
            <div class="flex flex-col items-center gap-2">
                <div class="animate-spin size-8 border-[3px] border-red-600 border-t-transparent rounded-full"></div>
                <span class="text-sm text-zinc-500">Memuat data...</span>
            </div>
        </div>

        {{-- Toolbar --}}
        @php
            $hasActiveFilters = $status !== '' || $start_date !== '' || $end_date !== '' || $sort !== 'desc';
        @endphp
        <div class="border-b border-zinc-200 bg-zinc-50/60">
            <div class="flex items-center justify-between gap-3 px-4 pt-4 sm:px-5">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-zinc-900">Daftar Izin</h2>
                    <p class="mt-0.5 text-xs text-zinc-500 tabular-nums">
                        @if ($data['total'] ?? false)
                            {{ $data['total'] }} pengajuan ditemukan
                        @else
                            Belum ada data
                        @endif
                    </p>
                </div>

                @can('izin.view.all')
                <flux:dropdown position="bottom" align="end">
                    <flux:button type="button" size="sm" variant="outline" icon="arrow-down-tray" icon:trailing="chevron-down" class="shrink-0 bg-white!">
                        Export
                    </flux:button>
                    <flux:menu class="min-w-44">
                        <flux:menu.item
                            icon="table-cells"
                            x-on:click="$wire.getExportGroups().then(groups => window.downloadGroupedIzinWorkbook(groups, 'laporan-izin.xlsx'))"
                            wire:loading.attr="disabled"
                            wire:target="getExportGroups"
                        >
                            Export Excel
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                @endcan
            </div>

            <div class="grid grid-cols-2 items-end gap-3 px-4 pb-4 pt-3.5 sm:px-5 lg:grid-cols-[10rem_10rem_1fr_1fr_auto]">
                <flux:field>
                    <flux:label class="text-xs! text-zinc-500!">Urutkan</flux:label>
                    <flux:select wire:model.live="sort" size="sm">
                        <flux:select.option value="desc">Terbaru</flux:select.option>
                        <flux:select.option value="asc">Terlama</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label class="text-xs! text-zinc-500!">Status</flux:label>
                    <flux:select wire:model.live="status" size="sm">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="2">Approved</flux:select.option>
                        <flux:select.option value="3">Rejected</flux:select.option>
                        <flux:select.option value="1">Pending</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label class="text-xs! text-zinc-500!">Dari tanggal</flux:label>
                    <flux:input type="date" wire:model.live="start_date" size="sm" max="{{ $end_date ?: null }}" />
                </flux:field>

                <flux:field>
                    <flux:label class="text-xs! text-zinc-500!">Sampai tanggal</flux:label>
                    <flux:input type="date" wire:model.live="end_date" size="sm" min="{{ $start_date ?: null }}" />
                </flux:field>

                <div class="col-span-2 lg:col-span-1">
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="arrow-path"
                        wire:click="resetFilters"
                        :disabled="! $hasActiveFilters"
                        class="w-full lg:w-auto"
                    >
                        Reset filter
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Table (desktop) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-225 md:min-w-full text-sm text-left">
                <thead class="bg-zinc-50 border-b border-zinc-200 text-[11px] uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Nama</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Kategori</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Alasan</th>
                        <th class="px-4 py-3 font-semibold text-center whitespace-nowrap">Status</th>
                        <th class="px-5 py-3 font-semibold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="pointer-events-none" class="divide-y divide-zinc-100">
                    @forelse ($this->data['data'] ?? [] as $izin)
                        @php
                            $statusLabel = match ($izin['status'] ?? null) {
                                '2' => ['text' => 'Approved', 'color' => 'green'],
                                '3' => ['text' => 'Rejected', 'color' => 'red'],
                                default => ['text' => 'Pending', 'color' => 'yellow'],
                            };
                        @endphp
                        <tr wire:key="izin-{{ $izin['id'] }}" class="transition hover:bg-zinc-50/70">
                            <td class="px-5 py-3.5 whitespace-nowrap text-zinc-700 tabular-nums">
                                {{ Carbon::parse($izin['start_date'])->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <flux:avatar size="xs" name="{{ $izin['user_name'] ?? '?' }}" />
                                    <span class="font-medium text-zinc-900">{{ $izin['user_name'] ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 min-w-48 text-zinc-700">
                                <span class="line-clamp-2">{{ $izin['reason'] ?? 'N/A' }}</span>
                            </td>
                            <td class="px-4 py-3.5 min-w-40 text-xs text-zinc-500">
                                <span class="line-clamp-2">{{ $izin['description'] ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <flux:badge color="{{ $statusLabel['color'] }}" size="sm" inset="top bottom">{{ $statusLabel['text'] }}</flux:badge>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button
                                        href="{{ route('izin.show', $izin['id']) }}"
                                        wire:navigate
                                        icon="eye"
                                        variant="outline"
                                        size="sm"
                                        class="cursor-pointer"
                                    >
                                        Detail
                                    </flux:button>
                                    <flux:tooltip content="Unduh PDF">
                                        <flux:button
                                            wire:click="generatePDF({{ $izin['id'] }})"
                                            icon="arrow-down-tray"
                                            variant="ghost"
                                            size="sm"
                                            class="cursor-pointer"
                                            aria-label="Unduh PDF"
                                        />
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <div class="flex flex-col items-center gap-2 text-zinc-400">
                                    <flux:icon name="inbox" class="size-8" />
                                    <p class="text-sm">Tidak ada data izin</p>
                                    @if ($hasActiveFilters)
                                        <flux:button size="xs" variant="subtle" wire:click="resetFilters">Reset filter</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Cards (mobile) --}}
        <div class="space-y-3 p-3 md:hidden" wire:loading.class="pointer-events-none">
            @forelse ($this->data['data'] ?? [] as $izin)
                @php
                    $admin = (int) ($izin['status_admin'] ?? 1);
                    $superadmin = (int) ($izin['status_superadmin'] ?? 1);

                    if ($admin === 3 || $superadmin === 3) {
                        $progress = 100;
                        $progressColor = 'bg-rose-500';
                    } elseif ($admin === 2 && $superadmin === 2) {
                        $progress = 100;
                        $progressColor = 'bg-emerald-500';
                    } elseif ($admin === 2 || $superadmin === 2) {
                        $progress = 50;
                        $progressColor = 'bg-blue-500';
                    } else {
                        $progress = 25;
                        $progressColor = 'bg-amber-500';
                    }

                    $statusLabel = match ($izin['status'] ?? null) {
                        '2' => ['text' => 'Approved', 'color' => 'green'],
                        '3' => ['text' => 'Rejected', 'color' => 'red'],
                        default => ['text' => 'Pending', 'color' => 'yellow'],
                    };
                @endphp
                <article wire:key="izin-mob-{{ $izin['id'] }}" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:avatar size="xs" name="{{ $izin['user_name'] ?? '?' }}" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-zinc-900">{{ $izin['user_name'] ?? 'N/A' }}</p>
                                <p class="text-xs text-zinc-500 tabular-nums">{{ Carbon::parse($izin['start_date'])->format('d M Y') }}</p>
                            </div>
                        </div>
                        <flux:badge color="{{ $statusLabel['color'] }}" size="sm" class="shrink-0">{{ $statusLabel['text'] }}</flux:badge>
                    </div>

                    <p class="mt-2.5 text-sm text-zinc-600 line-clamp-2">{{ $izin['reason'] ?? 'N/A' }}</p>

                    <div class="mt-3 flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100">
                            <div class="{{ $progressColor }} h-full rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                        </div>
                        <span class="w-9 text-right text-[11px] font-medium text-zinc-500 tabular-nums">{{ $progress }}%</span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-zinc-100 pt-3">
                        <a href="{{ route('izin.show', $izin['id']) }}" wire:navigate>
                            <flux:button icon="eye" variant="outline" size="sm" class="w-full cursor-pointer">
                                Detail
                            </flux:button>
                        </a>
                        <flux:button
                            wire:click="generatePDF({{ $izin['id'] }})"
                            icon="arrow-down-tray"
                            variant="outline"
                            size="sm"
                            class="w-full cursor-pointer"
                        >
                            PDF
                        </flux:button>
                    </div>
                </article>
            @empty
                <div class="flex flex-col items-center gap-2 py-10 text-zinc-400">
                    <flux:icon name="inbox" class="size-8" />
                    <p class="text-sm">Tidak ada data izin</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if ($data['last_page'] ?? false)
            <nav class="flex flex-col md:flex-row md:items-center md:justify-between p-4 gap-4 border-t border-zinc-100" aria-label="Table navigation">
                <span class="text-xs text-zinc-500 text-center md:text-left">
                    Menampilkan
                    <span class="font-semibold text-zinc-900 tabular-nums">{{ $data['from'] }}–{{ $data['to'] }}</span>
                    dari
                    <span class="font-semibold text-zinc-900 tabular-nums">{{ $data['total'] }}</span>
                </span>

                @php
                    $current = $data['current_page'];
                    $last = $data['last_page'];
                    $start = max($current - 2, 1);
                    $end = min($current + 2, $last);
                @endphp

                <ul class="flex flex-wrap md:flex-nowrap items-center justify-center md:justify-start -space-x-px text-sm">
                    <li>
                        <button
                            wire:click="goToPage({{ $current - 1 }})"
                            @disabled(! $data['prev_page_url'])
                            class="px-3 h-9 flex items-center justify-center border border-zinc-200 bg-white rounded-l-lg text-zinc-600 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                        >
                            <flux:icon name="chevron-left" class="size-4" />
                        </button>
                    </li>

                    @if ($start > 1)
                        <li>
                            <button
                                wire:click="goToPage(1)"
                                class="w-9 h-9 flex items-center justify-center border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 transition cursor-pointer"
                            >1</button>
                        </li>
                        @if ($start > 2)
                            <li>
                                <span class="w-9 h-9 flex items-center justify-center border border-zinc-200 bg-white text-zinc-400">…</span>
                            </li>
                        @endif
                    @endif

                    @for ($i = $start; $i <= $end; $i++)
                        <li>
                            <button
                                wire:click="goToPage({{ $i }})"
                                class="w-9 h-9 flex items-center justify-center border border-zinc-200 transition cursor-pointer
                                    {{ $i === $current ? 'bg-red-600 text-white border-red-600 font-semibold z-10' : 'bg-white text-zinc-600 hover:bg-zinc-50' }}"
                            >{{ $i }}</button>
                        </li>
                    @endfor

                    @if ($end < $last)
                        @if ($end < $last - 1)
                            <li>
                                <span class="w-9 h-9 flex items-center justify-center border border-zinc-200 bg-white text-zinc-400">…</span>
                            </li>
                        @endif
                        <li>
                            <button
                                wire:click="goToPage({{ $last }})"
                                class="w-9 h-9 flex items-center justify-center border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 transition cursor-pointer"
                            >{{ $last }}</button>
                        </li>
                    @endif

                    <li>
                        <button
                            wire:click="goToPage({{ $current + 1 }})"
                            @disabled(! $data['next_page_url'])
                            class="px-3 h-9 flex items-center justify-center border border-zinc-200 bg-white rounded-r-lg text-zinc-600 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                        >
                            <flux:icon name="chevron-right" class="size-4" />
                        </button>
                    </li>
                </ul>
            </nav>
        @endif
    </div>
</div>

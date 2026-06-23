<?php

use App\Services\IzinCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    #[On('izinAdded')]
    public function fetchData(): void
    {
        try {
            $response = Http::timeout(5)
                ->retry(3)
                ->get(config('services.api_izin').'/global/izin/list', $this->buildQuery());

            if (! $response->successful()) {
                Log::error('Izin API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->data = [];

                return;
            }

            $json = $response->json();



            if (! ($json['success'] ?? false)) {
                Toaster::error('Failed to fetch izin data from API.');
                Log::error('Izin API returned error', [
                    'message' => $json['message'] ?? null,
                    'error' => $json['error'] ?? null,
                ]);
                $this->data = $json ?? [];

                return;
            }

            $this->data = $json;
        } catch (\Throwable $e) {
            Toaster::error($e->getMessage() ?: 'Connection error while fetching izin data.');
            Log::error('Izin API connection error', [
                'message' => $e->getMessage(),
            ]);
            $this->data = [];
        }
    }

    public function generatePDF(int $id)
    {
        $cacheKey = 'pdf-preview-'.$id;

        if (Cache::has($cacheKey)) {
            return $this->streamPdf(Cache::get($cacheKey), "izin_{$id}.pdf");
        }

        $response = app(IzinCache::class)->detail((int) $id);

        if (($response['success'] ?? false) !== true) {
            Toaster::error('Gagal generate PDF. Data izin tidak ditemukan.');

            return;
        }

        $izin = $response['data'];
        $izin['admins_base64'] = $this->convertImageToBase64(data_get($izin, 'admins'));
        $izin['superadmins_base64'] = $this->convertImageToBase64(data_get($izin, 'superadmins'));
        $izin['pemohon_base64'] = $this->convertImageToBase64(data_get($izin, 'url_sign'));

        $pdf = Pdf::loadView('pdf.izin-pdf', ['izin' => $izin])->setPaper('A4', 'portrait');
        $pdfBase64 = 'data:application/pdf;base64,'.base64_encode($pdf->output());
        Cache::put($cacheKey, $pdfBase64, now()->addDay());

        $filename = 'Pengajuan Izin '.Carbon::parse($izin['start_date'])->format('d M Y').'.pdf';

        return $this->streamPdf($pdfBase64, $filename);
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

    protected function streamPdf(string $pdfBase64, string $filename)
    {
        $pdfBinary = base64_decode(str_replace('data:application/pdf;base64,', '', $pdfBase64));

        return response()->streamDownload(fn () => print ($pdfBinary), $filename);
    }

    protected function convertImageToBase64(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $imageContent = Http::timeout(120)->retry(3, 200)->get($url)->body();

            if (! $imageContent) {
                return null;
            }

            $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $imageContent);

            return 'data:'.$mimeType.';base64,'.base64_encode($imageContent);
        } catch (\Throwable) {
            return null;
        }
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
        <div class="p-4 border-b border-zinc-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <flux:icon name="table-cells" class="size-5 text-zinc-400 hidden sm:block" />
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-zinc-900">Daftar Izin</p>
                    <p class="text-xs text-zinc-500">
                        @if ($data['total'] ?? false)
                            {{ $data['total'] }} pengajuan ditemukan
                        @else
                            Belum ada data
                        @endif
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:items-center lg:gap-2">
                <flux:select wire:model.live="sort" size="sm" class="w-full lg:w-36">
                    <flux:select.option value="desc">Terbaru</flux:select.option>
                    <flux:select.option value="asc">Terlama</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="status" size="sm" class="w-full lg:w-36">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="2">Approved</flux:select.option>
                    <flux:select.option value="3">Rejected</flux:select.option>
                    <flux:select.option value="1">Pending</flux:select.option>
                </flux:select>

                <flux:input type="date" wire:model.live="start_date" size="sm" class="w-full lg:w-40" />
                <flux:input type="date" wire:model.live="end_date" size="sm" class="w-full lg:w-40" />
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-225 md:min-w-full text-sm text-left">
                <thead class="bg-zinc-50/80 border-b border-zinc-200 text-[11px] uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Nama</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Alasan</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Progress</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="pointer-events-none" class="divide-y divide-zinc-100">
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
                        <tr wire:key="izin-{{ $izin['id'] }}" class="hover:bg-zinc-50/60 transition">
                            <td class="px-4 py-3 whitespace-nowrap text-zinc-700 tabular-nums">
                                {{ Carbon::parse($izin['start_date'])->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="xs" name="{{ $izin['user_name'] ?? '?' }}" />
                                    <span class="font-medium text-zinc-900">{{ $izin['user_name'] ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 min-w-55 text-zinc-600">
                                <span class="line-clamp-2">{{ $izin['reason'] ?? 'N/A' }}</span>
                            </td>
                            <td class="px-4 py-3 min-w-40">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-zinc-100 rounded-full overflow-hidden">
                                        <div class="{{ $progressColor }} h-full rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-medium text-zinc-500 tabular-nums w-9 text-right">{{ $progress }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $statusLabel['color'] }}" size="sm">{{ $statusLabel['text'] }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:justify-end">
                                    <a href="{{ route('izin.show', $izin['id']) }}" wire:navigate>
                                        <flux:button icon="eye" variant="outline" size="sm" class="w-full cursor-pointer sm:w-auto">
                                            Detail
                                        </flux:button>
                                    </a>
                                    <flux:button
                                        wire:click="generatePDF({{ $izin['id'] }})"
                                        icon="arrow-down-tray"
                                        variant="outline"
                                        size="sm"
                                        class="w-full sm:w-auto cursor-pointer"
                                    >
                                        PDF
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-zinc-400">
                                    <flux:icon name="inbox" class="size-8" />
                                    <p class="text-sm">Tidak ada data izin</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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

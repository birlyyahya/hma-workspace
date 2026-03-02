<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public $data;

    public $page;
    public $perPage = 10;
    public $search = '';
    public $sort = 'desc';

    public $status = '';
    public $start_date = '';
    public $end_date = '';

    // from laporan pengajuan
    public $laporan = false;

    public function mount() {

        if (!$this->data) {
            if (!$this->laporan){
                $response = Http::get(env('API_IZIN') . '/global/izin/list?username='.Auth::user()->username.'&page='.$this->page.'&per_page='.$this->perPage.'&search_alasan='.$this->search.'&start_date='.$this->start_date.'&end_date='.$this->end_date.'&status='.$this->status.'&sort_order='.$this->sort)->json();
            }else {
                $response = Http::get(env('API_IZIN') . '/global/izin/list?page='.$this->page.'&per_page='.$this->perPage.'&search_name='.$this->search.'&start_date='.$this->start_date.'&end_date='.$this->end_date.'&status='.$this->status.'&sort_order='.$this->sort)->json();
            }

            if ($response['success']) {

            $this->data = $response;

            } else {
                $this->data = $response;

                Toaster::error('Failed to fetch izin data from API.');
                \Log::error('Izin API failed', [
                    'status' => $response['message'],
                    'body'   => $response['error'] ?? 'No Error',
                ]);

                return $this->data;
            }
        }

    }

    public function goToPage($page)
    {
    $this->page = $page;
    $this->fetchData();
    }

    #[On('izinSearchUpdated')]
    public function updatedSearch($value)
    {
        $this->search = $value;
        $this->page = 1;
        $this->fetchData();
    }

    #[On('izinSortUpdated')]
    public function updatedSort($value)
    {
        $this->sort = $value;
        $this->page = 1;
        $this->fetchData();
    }

    #[On('izinStatusUpdated')]
    public function updatedStatus($value)
    {
        $this->status = $value;
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedStartDate()
    {
        $this->page = 1;
        $this->fetchData();
    }

    public function updatedEndDate()
    {
        $this->page = 1;
        $this->fetchData();
    }

    #[On('izinAdded')]
    public function fetchData()
    {
        if (!$this->laporan){
            $response = Http::get(env('API_IZIN') . '/global/izin/list?username='.Auth::user()->username.'&page='.$this->page.'&per_page='.$this->perPage.'&search_alasan='.$this->search.'&start_date='.$this->start_date.'&end_date='.$this->end_date.'&status='.$this->status.'&sort_order='.$this->sort)->json();
        }else {
            $response = Http::get(env('API_IZIN') . '/global/izin/list?page='.$this->page.'&per_page='.$this->perPage.'&search_name='.$this->search.'&start_date='.$this->start_date.'&end_date='.$this->end_date.'&status='.$this->status.'&sort_order='.$this->sort)->json();
        }

        if ($response['success']) {
            $this->data = $response;

        } else {
            Toaster::error('Failed to fetch izin data from API.');

            \Log::error('Izin API failed', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
            ]);
        }
    }

    public function generatePDF($id)
    {
        $cacheKey = 'pdf-preview-' . $id;

        if (Cache::has($cacheKey)) {
            $pdfBase64 = Cache::get($cacheKey);


            $pdfBinary = base64_decode(
                str_replace('data:application/pdf;base64,', '', $pdfBase64)
            );

            return response()->streamDownload(
                fn () => print($pdfBinary),
                "izin_{$id}.pdf"
            );
        }

        $response = Http::get(env('API_IZIN') . '/global/izin/detail/' . $id)->json();

        if (($response['success'] ?? false) !== true) {
            Toaster::error('Gagal generate PDF. Data izin tidak ditemukan.');
            return;
        }


        $izin = $response['data'];
        Cache::remember(
            'ttd_user_' . Auth::user()->id,now()->addMonths(6), // key unik per user
            function () use ($izin) {
                return $izin['url_sign'];
            }
        );

        $izin['admins_base64'] = $this->convertImageToBase64(data_get($izin, 'admins'));
        $izin['superadmins_base64'] = $this->convertImageToBase64(data_get($izin, 'superadmins'));
        $izin['pemohon_base64'] = $this->convertImageToBase64(data_get($izin, 'url_sign'));

        $pdf = Pdf::loadView('pdf.izin-pdf', [
            'izin' => $izin ?? [],
        ])->setPaper('A4', 'portrait');
        $pdfBase64 = 'data:application/pdf;base64,' . base64_encode($pdf->output());
        Cache::put($cacheKey, $pdfBase64, now()->addDay());

        $pdfBinary = base64_decode(
            str_replace('data:application/pdf;base64,', '', $pdfBase64)
        );

        return response()->streamDownload(
            fn () => print($pdfBinary),
            "Pengajuan Izin ".Carbon::parse($izin['start_date'])->format('d M Y').".pdf"
        );
    }

    private function convertImageToBase64($url)
    {
        if (!$url) return null;

        try {
            $imageContent = Http::timeout(5)->get($url)->body();

            if (!$imageContent) return null;

            $mimeType = finfo_buffer(
                finfo_open(FILEINFO_MIME_TYPE),
                $imageContent
            );

            return 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);

        } catch (\Exception $e) {
            return null;
        }
    }

    public function placeholder(){
        return view('components.placeholder.ph_izin_table');
    }


} ?>

<div>
    <div class="bg-white/80 relative rounded-lg border border-zinc-200 overflow-hidden">
        <div wire:loading.flex class="absolute inset-0 z-20
                flex items-center justify-center
                bg-white/50 backdrop-blur-sm">

            <div class="flex flex-col items-center gap-2">
                <div class="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
                <span class="text-sm text-gray-600">Loading data...</span>
            </div>

        </div>
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex gap-3">
                <flux:label>Sort by</flux:label>
                <flux:select wire:model.live="sort" class="w-full md:w-48">
                    <option value="" disabled>Sort by</option>
                    <option value="asc">ASC</option>
                    <option value="desc">DESC</option>
                </flux:select>
            </div>

            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center md:w-auto md:gap-4">
                <flux:input type="date" wire:model.live='start_date' class="w-full sm:w-auto"></flux:input>
                <span class="hidden sm:inline">to</span>
                <flux:input type="date" wire:model.live='end_date' class="w-full sm:w-auto"></flux:input>
            </div>
            <flux:select wire:model.live="status" class="w-full md:w-48">
                <option value="" disabled>Status</option>
                <option value="2">Approved</option>
                <option value="3">Rejected</option>
                <option value="1">Pending</option>
            </flux:select>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[900px] md:min-w-full text-sm text-left text-gray-600 ">
                <thead class="bg-white text-xs uppercase shadow-sm text-gray-500 ">
                    <tr>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Tanggal</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Nama</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Alasan</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Progress</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 md:px-6 text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="pointer-events-none">
                    @foreach ($this->data['data'] ?? [] as $izin)
                    @php
                    $admin = (int) ($izin['status_admin'] ?? 1);
                    $superadmin = (int) ($izin['status_superadmin'] ?? 1);

                    $progress = 25;
                    $color = 'bg-blue-500';

                    // Cancel jika salah satu 3
                    if ($admin === 3 || $superadmin === 3) {
                    $progress = 100;
                    $color = 'bg-red-500';
                    }
                    // Approved jika keduanya 2
                    elseif ($admin === 2 && $superadmin === 2) {
                    $progress = 100;
                    $color = 'bg-blue-500';
                    }
                    // Salah satu sudah approve
                    elseif ($admin === 2 || $superadmin === 2) {
                    $progress = 50;
                    $color = 'bg-blue-500';
                    }
                    @endphp
                    <tr wire:key="{{ $izin['id'] }}" class="border-b border-gray-100 hover:bg-gray-50 transition">
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ Carbon::parse($izin['start_date'])->format('d M Y') }}</td>
                        <td class="px-3 py-3 md:px-6 font-medium text-gray-900 whitespace-nowrap">
                            {{ $izin['user_name'] ?? 'N/A' }}
                        </td>
                        <td class="px-3 py-3 md:px-6 min-w-[220px]">{{ $izin['reason'] ?? 'N/A' }}</td>
                        <td class="px-3 py-3 md:px-6 min-w-[140px]">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="{{ $color }} h-2 rounded-full transition-all duration-300" style="width: {{ $progress }}%">
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 md:px-6">
                            <div class="flex justify-start gap-2">
                            @if($izin['status'] === '2')
                            <flux:badge color="green" size="sm">Approved</flux:badge>
                            @elseif($izin['status'] === '1')
                            <flux:badge color="red" size="sm">Rejected</flux:badge>
                            @else
                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                            @endif
                            </div>
                        </td>
                        <td class="px-3 py-3 md:px-6 text-right">
                            <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:justify-end">
                                <a href="{{ route('izin.show', $izin['id']) }}" wire:navigate>
                                    <flux:button icon="eye" variant="outline" size="sm" class="w-full cursor-pointer sm:w-auto">View</flux:button>
                                </a>
                                <flux:button wire:click="generatePDF({{ $izin['id'] }})" icon="arrow-down-tray" variant="outline" size="sm" class="w-full sm:w-auto">
                                    Download PDF
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @if(empty($this->data['data']))
                    <tr>
                        <td colspan="6" class="px-3 py-3 md:px-6 text-center text-gray-400">
                            Tidak ada data izin
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <nav class="flex flex-col md:flex-row md:items-center md:justify-between p-4 gap-4" aria-label="Table navigation">

            <!-- Info -->
            <span class="text-sm text-gray-600 text-center md:text-left">
                Showing
                <span class="font-semibold text-gray-900">
                    {{ $this->data['from'] }}-{{ $this->data['to'] }}
                </span>
                of
                <span class="font-semibold text-gray-900">
                    {{ $this->data['total'] }}
                </span>
            </span>

            <!-- Pagination -->
            <ul class="flex flex-wrap md:flex-nowrap items-center justify-center md:justify-start gap-1 md:gap-0 md:-space-x-px text-sm">

                @php
                $current = $this->data['current_page'];
                $last = $this->data['last_page'];
                $start = max($current - 2, 1);
                $end = min($current + 2, $last);
                @endphp

                {{-- Previous --}}
                <li>
                    <button wire:click="goToPage({{ $current - 1 }})" @disabled(!$this->data['prev_page_url'])
                        class="px-3 h-9 flex items-center justify-center
                        border border-gray-300 bg-white
                        rounded-l-lg text-gray-700
                        hover:bg-gray-100 disabled:opacity-50">
                        Previous
                    </button>
                </li>

                {{-- First Page --}}
                @if ($start > 1)
                <li>
                    <button wire:click="goToPage(1)" class="w-9 h-9 flex items-center justify-center
                border border-gray-300 bg-white
                text-gray-700 hover:bg-gray-100">
                        1
                    </button>
                </li>

                @if ($start > 2)
                <li>
                    <span class="w-9 h-9 flex items-center justify-center
                        border border-gray-300 bg-white text-gray-500">
                        ...
                    </span>
                </li>
                @endif
                @endif

                {{-- Middle Pages --}}
                @for ($i = $start; $i <= $end; $i++) <li>
                    <button wire:click="goToPage({{ $i }})" class="w-9 h-9 flex items-center justify-center
                border border-gray-300
                {{ $i == $current
                    ? 'bg-gray-600 text-white font-semibold'
                    : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                        {{ $i }}
                    </button>
                    </li>
                    @endfor

                    {{-- Last Page --}}
                    @if ($end < $last) @if ($end < $last - 1) <li>
                        <span class="w-9 h-9 flex items-center justify-center
                        border border-gray-300 bg-white text-gray-500">
                            ...
                        </span>
                        </li>
                        @endif

                        <li>
                            <button wire:click="goToPage({{ $last }})" class="w-9 h-9 flex items-center justify-center
                border border-gray-300 bg-white
                text-gray-700 hover:bg-gray-100">
                                {{ $last }}
                            </button>
                        </li>
                        @endif

                        {{-- Next --}}
                        <li>
                            <button wire:click="goToPage({{ $current + 1 }})" @disabled(!$this->data['next_page_url'])
                                class="px-3 h-9 flex items-center justify-center
                                border border-gray-300 bg-white
                                rounded-r-lg text-gray-700
                                hover:bg-gray-100 disabled:opacity-50">
                                Next
                            </button>
                        </li>
            </ul>
        </nav>
    </div>
</div>

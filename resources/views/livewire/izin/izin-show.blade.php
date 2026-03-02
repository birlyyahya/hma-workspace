<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Izin Detail'])]
class extends Component {

    public $data;
    public $id;
    public $pdfPreview;

    public function mount() {
        // nanti fetch detail izin by id
        $response = Http::get(env('API_IZIN') . '/global/izin/detail/' . $this->id)->json();
        Cache::remember(
            'ttd_user_' . Auth::user()->id,now()->addMonths(6), // key unik per user
            function () use ($response) {
                return $response['data']['url_sign'];
            }
        );

        if (!$response['success']) {
            Toaster::error('Failed to fetch izin detail from API.');
            \Log::error('Izin Detail API failed', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
            ]);
        }

        $this->data = $response['data'] ?? null;
    }

    public function generatePDF($id)
    {
        $cacheKey = 'pdf-preview-' . $id;

        if (Cache::has($cacheKey)) {

            $this->pdfPreview = Cache::get($cacheKey);
            return;
        }
        $izin = $this->data;

        $izin['admins_base64'] = $this->convertImageToBase64(data_get($izin, 'admins'));
        $izin['superadmins_base64'] = $this->convertImageToBase64(data_get($izin, 'superadmins'));
        $izin['pemohon_base64'] = $this->convertImageToBase64(data_get($izin, 'url_sign'));

        $pdf = Pdf::loadView('pdf.izin-pdf', [
            'izin' => $izin,
        ])->setPaper('A4', 'portrait');

        $pdfBase64 = 'data:application/pdf;base64,' . base64_encode($pdf->output());

        Cache::put($cacheKey, $pdfBase64, now()->addDay());

        $this->pdfPreview = $pdfBase64;
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

}; ?>

<div>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">

            {{-- header --}}
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <a onclick="history.back()" class="cursor-pointer group inline-flex items-center gap-2 text-sm text-gray-500 hover:text-red-600 transition" wire:navigate>

                    <flux:icon name="arrow-left" class="size-5 transition-transform duration-300 group-hover:-translate-x-2 group-hover:animate-pulse">
                    </flux:icon>

                    <span>Back to Izin</span>

                </a>
                <div class="flex items-end gap-4">
                    <flex:description class="font-light text-gray-500 text-sm">Everything about your izin, in one place</flex:description>
                </div>
            </div>

            {{-- content --}}
            <div class="grid lg:grid-cols-3 grid-cols-1 gap-4">
                {{-- pdf reader --}}

                <div class="bg-white border rounded-lg p-4 col-span-2">
                    @if($pdfPreview)
                    <iframe src="{{ $this->pdfPreview }}" class="w-full h-[420px] sm:h-[500px] md:h-[600px] rounded-lg">
                    </iframe>
                    @else
                    <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 justify-center h-[420px] sm:h-[500px] md:h-[600px] text-gray-400 text-center sm:text-left">
                        <span>PDF preview akan muncul di sini</span>
                        <flux:button wire:click="generatePDF({{ $data['id'] }})" variant="primary" size="sm" class="cursor-pointer w-full sm:w-auto">
                            Preview PDF
                        </flux:button>
                    </div>
                    @endif
                </div>

                {{-- Detail --}}
                <div class="order-first lg:order-last">
                    <div class="bg-white p-4 space-y-4 border rounded-lg">
                        <div class="flex items-center gap-2 justify-between mb-6">
                            <flux:heading size="lg">Detail Pengajuan Izin</flux:heading>
                            @if($data['status'] === '2')
                            <flux:badge color="green" size="sm">Approved</flux:badge>
                            @elseif($data['status'] === '1')
                            <flux:badge color="red" size="sm">Rejected</flux:badge>
                            @else
                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                            @endif
                        </div>
                        <div>
                            <flux:input icon="user" value="{{ $data['users']['name'] ?? '-' }}" readonly></flux:input>
                        </div>
                        <div>
                            <flux:input icon="document-text" value="{{ $data['reason'] ?? '-' }}" readonly></flux:input>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="w-full">
                                <flux:input icon="calendar" value="{{ Carbon::parse($data['start_date'])->format('d M Y') }}" readonly class="w-full"></flux:input>
                            </div>
                            <span class="hidden sm:inline">-</span>
                            <div class="w-full">
                                <flux:input icon="calendar" value="{{ Carbon::parse($data['end_date'])->format('d M Y') }}" readonly class="w-full"></flux:input>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="w-full">
                                <flux:input icon="clock" value="{{ $data['start_time'] }}" readonly class="w-full"></flux:input>
                            </div>
                            <span class="hidden sm:inline">-</span>
                            <div class="w-full">
                                <flux:input icon="clock" value="{{ $data['end_time'] }}" readonly class="w-full"></flux:input>
                            </div>
                        </div>
                        <div>
                            <flux:textarea icon="check" readonly>{{ $data['description'] ?? '-' }}</flux:textarea>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

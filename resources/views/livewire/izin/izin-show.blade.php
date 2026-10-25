<?php

use App\Models\User;
use App\Services\IzinCache;
use App\Services\RemoteImageFetcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Izin Detail'])]
class extends Component {

    public $data;
    public $id;
    public $pdfPreview;

    public function mount() {
        $response = app(IzinCache::class)->detail((int) $this->id);

        if (! ($response['success'] ?? false)) {
            Toaster::error('Failed to fetch izin detail from API.');
            \Log::error('Izin Detail API failed', [
                'body' => $response['message'] ?? 'No message',
            ]);
        }

        $this->data = $response['data'] ?? null;
    }

    public function generatePDF($id)
    {
        $response = app(IzinCache::class)->detail((int) $id);
        $izin = ($response['success'] ?? false) ? $response['data'] : $this->data;

        $pemohon = User::with('role.department.parent')
            ->where('username', data_get($izin, 'users.username'))
            ->first();

        if (! $pemohon) {
            Toaster::error('Data pemohon tidak ditemukan.');

            return;
        }

        $approvers = $pemohon->izinApprovers();
        $izin['users'] = $pemohon->toArray();
        $izin['users']['signature'] = $this->signatureToDataUri(
            data_get($izin, 'url_sign') ?: $pemohon->signature
        );

        $izin['approval_signatures'] = $approvers->values()->map(function (User $approver) use ($izin) {
            $isDirector = $approver->hasRole(['direktur', 'director', 'gm']);
            $isManager = $approver->hasRole('manager');
            $fallback = $isDirector || $isManager
                ? data_get($izin, 'superadmins')
                : data_get($izin, 'admins');
            $signature = $approver->signature ?: $fallback;

            return [
                'title' => $isDirector || $isManager ? 'Disetujui Oleh' : 'Mengetahui Oleh',
                'role' => $isDirector ? 'Direktur' : ($isManager ? 'Manager' : 'Atasan Langsung'),
                'name' => $approver->name,
                'signature_base64' => $this->signatureToDataUri($signature),
            ];
        })->all();

        $pdf = Pdf::loadView('pdf.izin-pdf', [
            'izin' => $izin,
        ])->setPaper('A4', 'portrait');

        $pdfBase64 = 'data:application/pdf;base64,'.base64_encode($pdf->output());
        $this->pdfPreview = $pdfBase64;
    }

    private function signatureToDataUri(?string $signature): ?string
    {
        if (! $signature) {
            return null;
        }

        if (str_starts_with($signature, 'data:')) {
            return $signature;
        }

        $url = filter_var($signature, FILTER_VALIDATE_URL)
            ? $signature
            : Storage::disk('s3_izindar')->temporaryUrl(
                ltrim(str_replace('storage/izindar/', '', $signature), '/'),
                now()->addMinutes(5),
            );

        return $this->convertImageToBase64($url);
    }

    private function convertImageToBase64(?string $url): ?string
    {
        return app(RemoteImageFetcher::class)->toDataUri($url);
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

                <div class="bg-white border rounded-lg p-3 sm:p-4 lg:col-span-2">
                    @if($pdfPreview)
                    <iframe src="{{ $this->pdfPreview }}" class="w-full h-[420px] sm:h-[500px] md:h-[600px] rounded-lg">
                    </iframe>
                    @else
                    <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 justify-center h-[420px] sm:h-[500px] md:h-[600px] text-gray-400 text-center sm:text-left">
                        <span wire:loading.remove wire:target="generatePDF">PDF preview akan muncul di sini</span>
                        <span wire:loading wire:target="generatePDF">Sedang membuat PDF...</span>
                        <flux:button wire:click="generatePDF({{ $data['id'] }})" wire:loading.attr="disabled" wire:target="generatePDF" variant="primary" size="sm" class="cursor-pointer w-full sm:w-auto">
                            <span wire:loading.remove wire:target="generatePDF">Preview PDF</span>
                            <span wire:loading wire:target="generatePDF">Membuat PDF...</span>
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

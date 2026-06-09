<?php

use App\Services\IzinCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Ajukan Izin Cepat'])]
class extends Component {
    public string $start_date = '';
    public string $end_date = '';
    public string $start_time = '';
    public string $end_time = '';
    public string $alasan = '';
    public string $deskripsi = '';

    /** Step UI: form|success */
    public string $step = 'form';

    public ?int $createdIzinId = null;

    /**
     * @var array<int, array{value:string,label:string,icon:string}>
     */
    public array $jenisIzin = [
        ['value' => 'Sakit', 'label' => 'Sakit', 'icon' => 'heart'],
        ['value' => 'Pulang lebih awal', 'label' => 'Pulang Lebih Awal', 'icon' => 'arrow-uturn-left'],
        ['value' => 'Datang terlambat', 'label' => 'Datang Terlambat', 'icon' => 'clock'],
        ['value' => 'Tugas luar kantor', 'label' => 'Tugas Luar Kantor', 'icon' => 'briefcase'],
        ['value' => 'Dinas luar kota', 'label' => 'Dinas Luar Kota', 'icon' => 'map-pin'],
        ['value' => 'Lain-lain', 'label' => 'Lain-lain', 'icon' => 'ellipsis-horizontal'],
    ];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->start_date = Carbon::now()->format('Y-m-d');
        $this->end_date = Carbon::now()->format('Y-m-d');
        $this->start_time = '08:30';
        $this->end_time = '17:30';
        $this->alasan = '';
        $this->deskripsi = '';
    }

    public function buatLagi(): void
    {
        $this->resetForm();
        $this->createdIzinId = null;
        $this->step = 'form';
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'alasan' => ['required', 'string'],
            'deskripsi' => ['required', 'string', 'min:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'alasan.required' => 'Pilih jenis izin terlebih dahulu.',
            'deskripsi.min' => 'Deskripsi minimal 5 karakter.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        try {
            $response = Http::timeout(8)
                ->post(config('services.api_izin').'/global/izin/create-izin-saya', [
                    'start_date' => $this->start_date,
                    'end_date' => $this->end_date,
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'alasan' => $this->alasan,
                    'deskripsi' => $this->deskripsi,
                    'username' => Auth::user()->username,
                ])->json();
        } catch (\Throwable $e) {
            Log::error('QuickIzin create connection error', ['message' => $e->getMessage()]);
            Toaster::error('Gagal menghubungi server izin. Silakan coba lagi.');

            return;
        }

        if (! ($response['success'] ?? false)) {
            Toaster::error('Gagal mengajukan izin. Silakan coba lagi.');

            return;
        }

        $this->createdIzinId = $this->resolveCreatedIzinId($response);
        $cache = app(IzinCache::class);
        $cache->flushUser(Auth::user()->username);
        $cache->flushGroup();
        $cache->flushList();
        $this->dispatch('izinAdded');
        Toaster::success('Izin berhasil diajukan!');
        $this->step = 'success';
    }

    /**
     * Coba ambil ID izin yg baru dibuat. Bila response API tidak menyertakan ID,
     * fallback ke pengambilan izin terbaru milik user dari endpoint list.
     */
    protected function resolveCreatedIzinId(array $response): ?int
    {
        $id = data_get($response, 'data.id') ?? data_get($response, 'id');

        if ($id) {
            return (int) $id;
        }

        try {
            $latest = Http::timeout(5)->get(config('services.api_izin').'/global/izin/list', [
                'username' => Auth::user()->username,
                'page' => 1,
                'per_page' => 1,
                'sort_order' => 'desc',
            ])->json();

            return (int) (data_get($latest, 'data.0.id') ?? 0) ?: null;
        } catch (\Throwable $e) {
            Log::warning('QuickIzin resolve latest izin failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function downloadPdf()
    {
        if (! $this->createdIzinId) {
            Toaster::error('ID izin tidak ditemukan untuk diunduh.');

            return;
        }

        $cacheKey = 'pdf-preview-'.$this->createdIzinId;

        if (Cache::has($cacheKey)) {
            return $this->streamPdf(Cache::get($cacheKey), "izin_{$this->createdIzinId}.pdf");
        }

        $detail = app(IzinCache::class)->detail((int) $this->createdIzinId);

        if (! ($detail['success'] ?? false)) {
            Toaster::error('Gagal mengambil detail izin untuk PDF.');

            return;
        }

        $izin = $detail['data'];
        $izin['admins_base64'] = $this->convertImageToBase64(data_get($izin, 'admins'));
        $izin['superadmins_base64'] = $this->convertImageToBase64(data_get($izin, 'superadmins'));
        $izin['pemohon_base64'] = $this->convertImageToBase64(data_get($izin, 'url_sign'));

        $pdf = Pdf::loadView('pdf.izin-pdf', ['izin' => $izin])->setPaper('A4', 'portrait');
        $pdfBase64 = 'data:application/pdf;base64,'.base64_encode($pdf->output());
        Cache::put($cacheKey, $pdfBase64, now()->addDay());

        $filename = 'Pengajuan Izin '.Carbon::parse($izin['start_date'])->format('d M Y').'.pdf';

        return $this->streamPdf($pdfBase64, $filename);
    }

    protected function streamPdf(string $pdfBase64, string $filename)
    {
        $pdfBinary = base64_decode(str_replace('data:application/pdf;base64,', '', $pdfBase64));

        return response()->streamDownload(fn () => print $pdfBinary, $filename);
    }

    protected function convertImageToBase64(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $imageContent = Http::timeout(5)->get($url)->body();

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
    <div class="bg-zinc-50 min-h-screen">
        {{-- Top bar --}}
        <div class="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-zinc-200">
            <div class="max-w-md mx-auto px-4 py-3 flex items-center gap-3">
                <a href="{{ route('dashboard') }}" wire:navigate
                    class="size-9 rounded-full bg-zinc-100 hover:bg-zinc-200 flex items-center justify-center transition shrink-0">
                    <flux:icon name="chevron-left" class="size-5 text-zinc-700" />
                </a>
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-red-600 font-semibold">Shortcut</p>
                    <h1 class="text-base font-semibold text-zinc-900 leading-tight truncate">
                        @if ($step === 'form')
                            Ajukan Izin Cepat
                        @else
                            Pengajuan Berhasil
                        @endif
                    </h1>
                </div>
            </div>
        </div>

        <div class="max-w-md mx-auto px-4 py-5 pb-28">

            @if ($step === 'form')
                {{-- ============== FORM STEP ============== --}}
                <form wire:submit="save" class="space-y-4">

                    {{-- Pemohon card --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 flex items-center gap-3">
                        <flux:avatar size="md" name="{{ Auth::user()->name }}" />
                        <div class="min-w-0">
                            <p class="text-[11px] uppercase tracking-wide text-zinc-400 font-medium">Pemohon</p>
                            <p class="text-sm font-semibold text-zinc-900 truncate">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-zinc-500 truncate">{{ '@'.Auth::user()->username }}</p>
                        </div>
                    </div>

                    {{-- Jenis Izin chips --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:icon name="tag" class="size-4 text-zinc-400" />
                            <p class="text-sm font-semibold text-zinc-800">Jenis Izin</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($jenisIzin as $jenis)
                                @php($selected = $alasan === $jenis['value'])
                                <button
                                    type="button"
                                    wire:click="$set('alasan', '{{ $jenis['value'] }}')"
                                    @class([
                                        'flex items-center gap-2 px-3 py-2.5 rounded-xl border text-left text-xs font-medium transition cursor-pointer',
                                        'bg-red-50 border-red-300 text-red-700 ring-2 ring-red-200/60' => $selected,
                                        'bg-white border-zinc-200 text-zinc-700 hover:bg-zinc-50' => ! $selected,
                                    ])
                                >
                                    <flux:icon name="{{ $jenis['icon'] }}" class="size-4 shrink-0" />
                                    <span class="truncate">{{ $jenis['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('alasan') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    {{-- Tanggal --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:icon name="calendar" class="size-4 text-zinc-400" />
                            <p class="text-sm font-semibold text-zinc-800">Periode Izin</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <flux:label class="text-[11px] text-zinc-500 mb-1">Mulai</flux:label>
                                <flux:input type="date" wire:model="start_date" />
                            </div>
                            <div>
                                <flux:label class="text-[11px] text-zinc-500 mb-1">Selesai</flux:label>
                                <flux:input type="date" wire:model="end_date" />
                            </div>
                        </div>
                        @error('end_date') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    {{-- Jam --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:icon name="clock" class="size-4 text-zinc-400" />
                            <p class="text-sm font-semibold text-zinc-800">Jam</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <flux:label class="text-[11px] text-zinc-500 mb-1">Mulai</flux:label>
                                <flux:input type="time" wire:model="start_time" />
                            </div>
                            <div>
                                <flux:label class="text-[11px] text-zinc-500 mb-1">Selesai</flux:label>
                                <flux:input type="time" wire:model="end_time" />
                            </div>
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <flux:icon name="document-text" class="size-4 text-zinc-400" />
                            <p class="text-sm font-semibold text-zinc-800">Deskripsi</p>
                        </div>
                        <flux:textarea
                            wire:model="deskripsi"
                            rows="4"
                            placeholder="Jelaskan alasan pengajuan izin Anda..."
                        />
                        @error('deskripsi') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </form>

                {{-- Sticky submit --}}
                <div class="fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-zinc-200 px-4 py-3">
                    <div class="max-w-md mx-auto">
                        <flux:button
                            wire:click="save"
                            variant="primary"
                            icon="paper-airplane"
                            class="w-full cursor-pointer"
                            wire:loading.attr="disabled"
                            wire:target="save"
                        >
                            <span wire:loading.remove wire:target="save">Ajukan Izin</span>
                            <span wire:loading wire:target="save">Mengajukan...</span>
                        </flux:button>
                    </div>
                </div>

            @else
                {{-- ============== SUCCESS STEP ============== --}}
                <div class="space-y-4">

                    {{-- Hero success --}}
                    <div class="rounded-2xl bg-linear-to-br from-emerald-500 to-emerald-600 p-6 text-white text-center relative overflow-hidden">
                        <div class="pointer-events-none absolute inset-0">
                            <div class="absolute -right-10 -top-10 size-40 rounded-full bg-white/15 blur-2xl"></div>
                            <div class="absolute -left-10 -bottom-10 size-40 rounded-full bg-black/10 blur-2xl"></div>
                        </div>
                        <div class="relative">
                            <div class="size-16 mx-auto rounded-full bg-white/20 ring-2 ring-white/40 flex items-center justify-center">
                                <flux:icon name="check" class="size-8 text-white" />
                            </div>
                            <h2 class="mt-4 text-lg font-semibold">Izin Berhasil Diajukan</h2>
                            <p class="mt-1 text-sm text-white/85">
                                Pengajuan Anda telah masuk ke sistem dan menunggu persetujuan.
                            </p>
                        </div>
                    </div>

                    {{-- Detail summary --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 divide-y divide-zinc-100">
                        <div class="flex items-center justify-between py-2">
                            <span class="text-xs text-zinc-500">Jenis Izin</span>
                            <span class="text-sm font-medium text-zinc-900">{{ $alasan }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-xs text-zinc-500">Mulai</span>
                            <span class="text-sm font-medium text-zinc-900 tabular-nums">
                                {{ Carbon::parse($start_date)->format('d M Y') }} {{ $start_time }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-xs text-zinc-500">Selesai</span>
                            <span class="text-sm font-medium text-zinc-900 tabular-nums">
                                {{ Carbon::parse($end_date)->format('d M Y') }} {{ $end_time }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-xs text-zinc-500">Status</span>
                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="space-y-2">
                        @if ($createdIzinId)
                            <flux:button
                                wire:click="downloadPdf"
                                variant="primary"
                                icon="arrow-down-tray"
                                class="w-full cursor-pointer"
                                wire:loading.attr="disabled"
                                wire:target="downloadPdf"
                            >
                                <span wire:loading.remove wire:target="downloadPdf">Unduh PDF</span>
                                <span wire:loading wire:target="downloadPdf">Menyiapkan PDF...</span>
                            </flux:button>
                            <a href="{{ route('izin.show', $createdIzinId) }}" wire:navigate class="block">
                                <flux:button variant="outline" icon="eye" class="w-full cursor-pointer">
                                    Lihat Detail Izin
                                </flux:button>
                            </a>
                        @else
                            <a href="{{ route('izin') }}" wire:navigate class="block">
                                <flux:button variant="primary" icon="list-bullet" class="w-full cursor-pointer">
                                    Buka Daftar Izin
                                </flux:button>
                            </a>
                            <p class="text-[11px] text-center text-zinc-500">
                                Unduh PDF tersedia di halaman daftar izin.
                            </p>
                        @endif

                        <div class="grid grid-cols-2 gap-2 pt-2">
                            <flux:button
                                wire:click="buatLagi"
                                variant="outline"
                                icon="plus"
                                class="w-full cursor-pointer"
                            >
                                Buat Lagi
                            </flux:button>
                            <a href="{{ route('dashboard') }}" wire:navigate class="block">
                                <flux:button variant="outline" icon="home" class="w-full cursor-pointer">
                                    Dashboard
                                </flux:button>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

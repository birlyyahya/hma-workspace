<?php

use App\Services\IzinCache;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public string $start_date = '';
    public string $end_date = '';
    public string $start_time = '';
    public string $end_time = '';
    public string $alasan = '';
    public string $deskripsi = '';

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
        $this->start_date = Carbon::now()->format('Y-m-d');
        $this->end_date = Carbon::now()->format('Y-m-d');
        $this->start_time = '08:30';
        $this->end_time = '17:30';
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
            Log::error('Izin Create API connection error', ['message' => $e->getMessage()]);
            Toaster::error('Gagal menghubungi server izin. Silakan coba lagi.');

            return;
        }

        if (! ($response['success'] ?? false)) {
            Toaster::error('Gagal mengajukan izin. Silakan coba lagi.');

            return;
        }

        Toaster::success('Izin berhasil diajukan!');
        $cache = app(IzinCache::class);
        $cache->flushUser(Auth::user()->username);
        $cache->flushGroup();
        $cache->flushList();
        $this->dispatch('izinAdded');
        $this->reset(['alasan', 'deskripsi']);
        Flux::modal('form-izin-modal')->close();
    }
}; ?>

<div>
    <flux:modal name="form-izin-modal" class="w-full max-w-xl">
        <form wire:submit="save" class="space-y-5">
            {{-- Header --}}
            <div class="flex items-start gap-3 pb-4 border-b border-zinc-200">
                <div class="size-10 rounded-xl bg-red-50 ring-1 ring-red-100 flex items-center justify-center">
                    <flux:icon name="document-plus" class="size-5 text-red-600" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg" class="text-zinc-900 leading-tight">Pengajuan Izin</flux:heading>
                    <flux:description class="text-xs text-zinc-500">
                        Isi formulir di bawah untuk mengajukan permohonan izin.
                    </flux:description>
                </div>
            </div>

            {{-- Pemohon --}}
            <flux:input
                disabled
                readonly
                label="Nama Pemohon"
                icon="user"
                :value="Auth::user()->name"
            />

            {{-- Tanggal --}}
            <div>
                <flux:label class="mb-1.5 flex items-center gap-1.5">
                    <flux:icon name="calendar" class="size-4 text-zinc-400" />
                    Periode Izin
                </flux:label>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <flux:input type="date" wire:model="start_date" placeholder="Tanggal mulai" />
                    <flux:input type="date" wire:model="end_date" placeholder="Tanggal selesai" />
                </div>
                @error('start_date') <flux:error>{{ $message }}</flux:error> @enderror
                @error('end_date') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            {{-- Jam --}}
            <div>
                <flux:label class="mb-1.5 flex items-center gap-1.5">
                    <flux:icon name="clock" class="size-4 text-zinc-400" />
                    Jam
                </flux:label>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <flux:input type="time" wire:model="start_time" placeholder="Jam mulai" />
                    <flux:input type="time" wire:model="end_time" placeholder="Jam selesai" />
                </div>
            </div>

            {{-- Jenis Izin --}}
            <flux:select label="Jenis Izin" wire:model="alasan" placeholder="Pilih jenis izin">
                @foreach ($jenisIzin as $jenis)
                    <flux:select.option value="{{ $jenis['value'] }}">{{ $jenis['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Deskripsi --}}
            <flux:textarea
                label="Deskripsi"
                wire:model="deskripsi"
                placeholder="Jelaskan alasan pengajuan izin Anda..."
                rows="4"
            />

            {{-- Actions --}}
            <div class="flex flex-col-reverse gap-2 pt-2 border-t border-zinc-100 sm:flex-row sm:justify-end sm:gap-3">
                <flux:button
                    type="button"
                    variant="outline"
                    class="w-full sm:w-auto cursor-pointer"
                    x-on:click="$flux.modal('form-izin-modal').close()"
                >
                    Batal
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="paper-airplane"
                    class="w-full sm:w-auto cursor-pointer"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">Ajukan Izin</span>
                    <span wire:loading wire:target="save">Mengajukan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

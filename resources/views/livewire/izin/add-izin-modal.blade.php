<?php

use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $start_date;
    public $end_date;
    public $start_time;
    public $end_time;
    public $alasan;
    public $deskripsi;

    public $query;
    public $name = [];

    public function mount(){
        $this->start_date = Carbon::now()->format('Y-m-d');
        $this->end_date = Carbon::now()->format('Y-m-d');
        $this->start_time = Carbon::createFromTimeString('08:30')->format('H:i');
        $this->end_time = Carbon::createFromTimeString('17:30')->format('H:i');
    }

    public function save(){

        $response = Http::post(env('API_IZIN') . '/global/izin/create-izin-saya', [
               "start_date" => $this->start_date,
                "end_date" => $this->end_date,
                "start_time" => $this->start_time,
                "end_time" => $this->end_time,
                "alasan" => $this->alasan,
                "deskripsi" => $this->deskripsi,
                "username" => Auth::user()->username
        ]);

        $response = $response->json();

        if($response['success']){
            Toaster::success('Izin berhasil diajukan!');

            $this->dispatch('izinAdded');
            $this->reset(['start_date', 'end_date', 'start_time', 'end_time', 'alasan', 'deskripsi']);
            Flux::modal('form-izin-modal')->close();

        } else {
            Toaster::error('Gagal mengajukan izin. Silakan coba lagi.');

        }
    }

    public function updatedQuery(){
        $this->name = User::where('name', 'like', '%' . $this->query . '%')->get()->toArray();
        if (empty($this->query)) {
            $this->name = '';
        }
    }

    public function selectUser($user){
        $this->query = $user['name'];
        $this->name = [];
    }

}; ?>

<div>
    <flux:modal name="form-izin-modal" class="w-full max-w-xl">
        <div class="space-y-6">
            <flux:input disabled label="Nama Lengkap" :value="Auth::user()->name" readonly placeholder="Masukkan nama lengkap" />
            <div class="grid grid-cols-1 gap-2 w-full sm:grid-cols-2">
                <flux:input label="Tanggal Mulai" type="date" wire:model="start_date" class="w-full" />
                <flux:input label="Tanggal Selesai" type="date" wire:model="end_date" class="w-full" />
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <flux:input label="Tanggal Mulai" type="time" wire:model="start_time" class="w-full" />
                <flux:input label="Tanggal Selesai" type="time" wire:model="end_time" class="w-full" />
            </div>
            <flux:select label="Jenis Izin" wire:model="alasan">
                <option value="">Pilih jenis izin</option>
                <option value="sakit">Sakit</option>
                <option value="Pulang lebih awal">Pulang Lebih Awal</option>
                <option value="datang terlambat">Datang Terlambat</option>
                <option value="Tugas luar kantor">Tugas Luar Kantor</option>
                <option value="Dinas luar kota">Dinas luar kota</option>
                <option value="Lain-lain">Lain-lain</option>
            </flux:select>
            <flux:textarea label="Deskripsi" wire:model='deskripsi'></flux:textarea>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                <flux:button variant="outline" class="px-4 py-2 rounded-md w-full sm:w-auto" x-on:click="$flux.modal('form-izin-modal').close()">Batal</flux:button>
                <flux:button variant="primary" class="px-4 py-2 rounded-md w-full sm:w-auto" wire:click="save">Ajukan Izin</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="laporan-pengajuan-izin-modal" class="overflow-visible">
        <div class="space-y-6">
            <flux:heading size="lg">Laporan Pengajuan Izin</flux:heading>
            <flux:separator></flux:separator>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input label="From" type="date"></flux:input>
                <flux:input label="To" type="date"></flux:input>
            </div>
            <div class="relative">
                <flux:input wire:model.live='query' label="Nama karyawan"></flux:input>
                @if(!empty($name))
                <ul class="absolute z-50 max-h-30 overflow-auto p-0 w-full bg-white border border-gray-200 rounded-md shadow-lg">
                    @foreach($name as $result)
                    <li class="px-4 py-2 text-xs hover:bg-gray-100 cursor-pointer flex items-center gap-2" wire:click="selectUser(@js($result))">
                        <flux:avatar name="{{ $result['name'] }}" size="xs" />{{ $result['name'] }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
            <flux:button variant="primary" class="px-4 py-2 rounded-md w-full sm:w-auto">Tampilkan Laporan</flux:button>
        </div>
    </flux:modal>
</div>

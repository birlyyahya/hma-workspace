<?php

use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $activeTab = 'Tugas luar kantor';
    public $report;
    public $data = [];
    public $ready = false;

    public function mount() {

            $this->data = collect(Cache::get('izin_widget_' . Auth::user()->username)['group'] ?? [])
            ->mapWithKeys(fn ($v, $k) => [trim($k) => $v])
            ->toArray();
            if (!empty($this->data)) {
                $this->ready = true;
            }
    }

    #[On('widget-pengajuan')]
    public function widgetPengajuan($data) {
        $this->data = collect($data)
            ->mapWithKeys(fn ($v, $k) => [trim($k) => $v])
            ->toArray();
        $this->ready = true;
    }

    public function setTab($tab)
    {

        $this->activeTab = $tab;
    }
}; ?>

<div>
    <div class="bg-white rounded-2xl shadow p-6 h-fulloverflow-hidden ">
        {{-- Header --}}
        <div class="mb-4 flex items-center gap-1">
            <flux:heading size="lg" class="text-lg font-semibold">Laporan Izin Karyawan</flux:heading>
            <flux:tooltip content="Ringkasan Status Pengajuan Izin Karyawan" placement="top">
                <flux:icon name="exclamation-circle" variant="outline" class="size-5" />
            </flux:tooltip>
        </div>


        <div x-data class="relative">
            {{-- Tabs --}}
            <div x-ref="tabs" class="w-full overflow-x-auto">
                <div class="border-b mb-4 flex gap-4 min-w-max">
                     @if(isset($data['Tugas luar kantor']))
                    <button wire:click="setTab('Tugas luar kantor')" class="text-sm cursor-pointer pb-2 border-b-2 {{ $activeTab === 'Tugas luar kantor' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500' }}">
                        Tugas Luar Kantor <flux:badge size="xs" variant="pill" :color="$activeTab === 'Tugas luar kantor' ? 'red' : 'gray'" class="">{{ count($data['Tugas luar kantor']) }}</flux:badge>
                    </button>
                        @endif
                        @if(isset($data['Sakit']))
                    <button wire:click="setTab('Sakit')" class="text-sm cursor-pointer pb-2 border-b-2 {{ $activeTab === 'Sakit' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500' }}">
                        Sakit <flux:badge size="xs" variant="pill" :color="$activeTab === 'Sakit' ? 'red' : 'gray'" class="">{{ count($data['Sakit']) }}</flux:badge>
                    </button>
                        @endif
                    @if(isset($data['Dinas luar kota']))
                    <button wire:click="setTab('Dinas luar kota')" class="text-sm cursor-pointer pb-2 border-b-2 {{ $activeTab === 'Dinas luar kota' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500' }}">
                        Dinas Luar Kota
                        <flux:badge size="xs" variant="pill" :color="$activeTab === 'Dinas luar kota' ? 'red' : 'gray'">{{ count($data['Dinas luar kota']) }}</flux:badge>
                        @endif
                        @if(isset($data['Datang terlambat']))
                        <button wire:click="setTab('Datang terlambat')" class="text-sm cursor-pointer pb-2 border-b-2 {{ $activeTab === 'Datang terlambat' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500' }}">
                            Terlambat
                            <flux:badge size="xs" variant="pill" :color="$activeTab === 'Datang terlambat' ? 'red' : 'gray'">{{ count($data['Datang terlambat']) }}</flux:badge>
                            @endif
                            @if(isset($data['Pulang lebih awal']))
                            <button wire:click="setTab('Pulang lebih awal')" class="text-sm cursor-pointer pb-2 border-b-2 {{ $activeTab === 'Pulang lebih awal' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500' }}">
                                Pulang Lebih Awal
                                <flux:badge size="xs" variant="pill" :color="$activeTab === 'Pulang lebih awal' ? 'red' : 'gray'">{{ count($data['Pulang lebih awal']) }}</flux:badge>
                                @endif
                                @if(isset($data['Lain-lain']))
                                <button wire:click="setTab('Lain-lain')" class="text-sm cursor-pointer pb-2 border-b-2 {{ $activeTab === 'Lain-lain' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500' }}">
                                    Lain-lain
                                    <flux:badge size="xs" variant="pill" :color="$activeTab === 'Lain-lain' ? 'red' : 'gray'">{{ count($data['Lain-lain']) }}</flux:badge>
                                    @endif
                </div>
            </div>

        </div>

        {{-- Content --}}
        <div class="space-y-3 max-h-40 overflow-y-auto">
            @forelse($this->data[$this->activeTab] ?? [] as $item )
            <div class="p-4 border rounded-xl bg-gray-50">
                <div class="flex justify-between items-center">
                    <div class="font-medium">{{ $item['username'] }}</div>
                    <div class="flex justify-center items-center text-xs text-gray-500">
                        <div class=" text-gray-500">{{ \Carbon\Carbon::parse($item['start_date'])->format('d M Y') }} {{ $item['start_time'] }}</div>
                        <div class="mx-2">-</div>
                        <div class=" text-gray-500">{{ \Carbon\Carbon::parse($item['end_date'])->format('d M Y') }} {{ $item['end_time'] }}</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500">{{ $item['description'] }}</div>
            </div>
            @empty
            <div class="text-gray-400 text-sm">Tidak ada pengajuan</div>
            @endforelse
        </div>
    </div>
</div>

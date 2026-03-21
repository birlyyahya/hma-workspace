<?php

use App\Livewire\Forms\SpectechForm;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public SpectechForm $form;

    public $totalproject;
    public $progress;
    public $spectech;
    public $loadingSpectech = false;
    public $id;

    public function deleteSpectech($id){
        try {
            Http::delete(env('API_PROJECT') . 'activity-categories/' . $id)->json();

            $this->spectech = collect($this->spectech)
            ->reject(fn($item) => $item['id'] == $id)
            ->values()
            ->toArray();

            Cache::forget('project_data_show_' . $this->id);

            $this->dispatch('projectLoad');

            Toaster::success('Spectech deleted successfully');
        }catch(\Exception $e){
            Toaster::error('Failed to delete spectech: ' . $e->getMessage());
            \Log::error('Failed to delete spectech: ' . $e->getMessage());
        }
    }

    public function create(){
        $response = $this->form->store($this->id);

        if($response['status'] === 201) {
          $this->spectech = collect($this->spectech)
            ->push([
                "id" => $response['data']['id'],
                "name" => $response['data']['name'],
                "qty_total" => $response['data']['qty_total'],
                "qty_recived" => $response['data']['qty_recived'],
                "total_nominal" => $response['data']['total_nominal'],
                "qty_nominal" => $response['data']['qty_nominal'],
                "percentage" => $response['data']['percentage'],
                "note" => $response['data']['note'],
                "images" => $response['data']['images'],
            ])
            ->toArray();
            $this->reset('form');
            Cache::forget('project_data_show_' . $this->id);
            $this->dispatch('projectLoad');

            Toaster::success('Spectech created successfully');
            Flux::modal('addSpectech')->close();
        } else {
            Toaster::error(getErrorMessages($response['errors']));
            \Log::error('Spectech API failed', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
            ]);
        }

    }

}; ?>

<div>
    <div class="space-y-6 grid grid-cols-3 gap-4">
        <div class="space-y-6 col-span-2">
            @if(!$this->loadingSpectech)
            @forelse ($this->spectech as $data)
            <div class="w-full max-w-full bg-white border border-gray-200 rounded-xl p-6">
                <!-- Top -->
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $data['name'] }}
                            </h3>
                            <!-- Status -->
                            <span class="px-3 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">
                                Income
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">
                            {{ $data['qty_recived'] ?? 0 }} / {{ $data['qty_total'] ?? 0 }} Barang Datang
                            <span class="mx-2">•</span>
                            Gudang G3
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            barang sudah digudang namun belum aktivasi dan instalasi
                        </p>
                    </div>

                    <!-- Avatar + menu -->
                    <div class="flex items-center gap-3">

                        <div class="flex -space-x-2">
                            <img src="https://i.pravatar.cc/40?img=1" class="w-8 h-8 rounded-full border-2 border-white">

                            <img src="https://i.pravatar.cc/40?img=2" class="w-8 h-8 rounded-full border-2 border-white">

                            <img src="https://i.pravatar.cc/40?img=3" class="w-8 h-8 rounded-full border-2 border-white">
                        </div>

                        <flux:dropdown wire:key="spectech-{{ $data['id'] }}">
                            <flux:button variant="ghost" size="sm" class="cursor-pointer text-gray-400 hover:text-gray-600 text-xl leading-none">
                                ⋮
                            </flux:button>

                            <flux:navmenu>
                                <flux:navmenu.item href="#">Edit</flux:navmenu.item>
                                <flux:navmenu.item class="cursor-pointer" wire:click="deleteSpectech({{ $data['id'] }})">Delete</flux:navmenu.item>
                            </flux:navmenu>
                        </flux:dropdown>

                    </div>

                </div>


                <!-- Progress -->
                <div class="mt-5">

                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">Progress</span>
                        <span class="text-sm font-medium text-gray-800">{{ $data['percentage'] }}%</span>
                    </div>

                    <!-- Segmented Progress -->
                    <div class="flex gap-1">
                        @php
                        $total = 26;
                        $progress = $data['percentage'];
                        $filled = round($progress / 100 * $total);
                        @endphp

                        <div class="flex gap-1">
                            @for ($i=1; $i <= $total; $i++) <div class="w-6 h-6 rounded-md {{ $i <= $filled ? 'bg-red-700' : 'bg-gray-200' }}">
                        </div>
                        @endfor
                    </div>

                </div>

            </div>

        </div>
        @empty
        <p class="text-gray-400 text-sm">Tidak ada spectech</p>
        @endforelse
        @else
        <p class="text-gray-400 text-sm">Loading...</p>
        @endif
    </div>
    <div class="space-y-6">
        <flux:modal.trigger name="addSpectech">
            <flux:button variant="primary" icon="plus" class="w-full mb-4">Add Spectech</flux:button>
        </flux:modal.trigger>
        <div class="bg-white rounded-xl p-6 border border-zinc-200 space-y-6">

            <!-- Header -->
            <div>
                <flux:heading size="lg" class="text-base font-medium">
                    Progress Pekerjaan
                </flux:heading>

                <flux:text class="text-zinc-500 text-sm">
                    Persentase dihitung dari nilai barang yang telah diterima dibandingkan total nilai proyek.
                </flux:text>
            </div>

            <!-- Progress Center -->
            <div class="text-center space-y-2">

                <div class="text-3xl font-semibold text-zinc-900">
                    {{ $this->progress }}%
                </div>

                <flux:text class="text-zinc-500 text-sm">
                    Progress Proyek
                </flux:text>

            </div>

            <!-- Progress Bar -->
            <div class="space-y-2">

                <div class="w-full h-3 bg-zinc-100 rounded-full overflow-hidden">
                    <div class="h-full bg-red-700 rounded-full" style="width:35%"></div>
                </div>

            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2">

                <div>
                    <flux:text class="text-xs text-zinc-500">
                        Nilai Barang Diterima
                    </flux:text>

                    <p class="text-xs font-semibold text-zinc-900">
                        Rp {{ number_format(collect($this->spectech)->sum('qty_nominal'), 2, ',', '.') }}
                    </p>
                </div>

                <div class="text-right">
                    <flux:text class="text-xs text-zinc-500">
                        Total Nilai Proyek
                    </flux:text>

                    <p class="font-semibold text-xs text-zinc-900">
                        Rp {{ number_format($totalproject, 2, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-white border border-zinc-200 rounded-xl p-6 space-y-6">

            <!-- Header -->
            <div>
                <flux:heading size="lg" class="text-[1rem] font-medium">
                    Cara Perhitungan Progress
                </flux:heading>

                <flux:text class="text-zinc-500 text-sm">
                    Progress pekerjaan dihitung berdasarkan nilai barang yang telah diterima dibandingkan dengan total nilai proyek.
                </flux:text>
            </div>


            <!-- Formula -->
            <div class="bg-zinc-50 rounded-lg p-4 text-center">

                <flux:text class="text-sm text-zinc-600">
                    Rumus Progress
                </flux:text>

                <p class="text-lg font-semibold text-zinc-800 mt-2">
                    (Nilai Barang Diterima ÷ Total Nilai Project) × 100%
                </p>

            </div>


            <!-- Example -->
            <div class="space-y-3">

                <flux:text class="text-sm font-medium text-zinc-700">
                    Contoh Perhitungan
                </flux:text>

                <div class="grid grid-cols-2 gap-4 text-sm">

                    <div class="bg-zinc-50 rounded-lg p-3">
                        <flux:text class="text-zinc-500 text-xs">
                            Nilai Barang Diterima
                        </flux:text>
                        <p class="font-semibold text-zinc-800">
                            Rp 35.000.000
                        </p>
                    </div>

                    <div class="bg-zinc-50 rounded-lg p-3">
                        <flux:text class="text-zinc-500 text-xs">
                            Total Nilai Project
                        </flux:text>
                        <p class="font-semibold text-zinc-800">
                            Rp 100.000.000
                        </p>
                    </div>

                </div>

                <!-- Result -->
                <div class="bg-red-50 border border-red-100 rounded-lg p-3 text-center">
                    <flux:text class="text-red-700 text-sm">
                        Progress Proyek
                    </flux:text>
                    <p class="text-xl font-semibold text-red-700">
                        35%
                    </p>
                </div>

            </div>
        </div>

        {{-- @if(!$this->loadingSpectech)
        <div class="bg-white rounded-2xl shadow-sm border p-6 space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">AI Project Analysis</h2>
                    <p class="text-sm text-gray-500">Generated by Gemini</p>
                </div>
            </div>


            <!-- Insight -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <p class="text-sm font-semibold text-blue-700 mb-2">
                    Insight Utama
                </p>

                <p class="text-sm text-gray-700 leading-relaxed">
                     {{ $this->gemini['Analisis_kontribusi'] }}
        </p>
    </div>

    <!-- Analisis Pareto -->
    <div class="bg-purple-50 border border-purple-100 rounded-xl p-4">
        <p class="text-sm font-semibold text-purple-700 mb-2">
            Analisis Kontribusi Barang (Pareto)
        </p>
        <p class="text-sm text-gray-700 mt-2">
            {{ $this->gemini['item_paling_mempengaruhi'] }}
        </p>
    </div>


    <div class="bg-purple-50 border border-purple-100 rounded-xl p-4">
        <p class="text-sm font-semibold text-purple-700 mb-2">
            Potensi keterlambatan
        </p>
        <p class="text-sm text-gray-700 mt-2">
            {{ $this->gemini['keterlambatan'] }}
        </p>
    </div>

    <!-- Rekomendasi -->
    <div class="bg-green-50 border border-green-100 rounded-xl p-4">
        <p class="text-sm font-semibold text-green-700 mb-2">
            Rekomendasi AI
        </p>
        <ul class="list-disc ml-5 text-sm space-y-1">
            @foreach($this->gemini['kesimpulan'] as $point)
            <li>{{ $point }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif --}}
</div>


<flux:modal name="addSpectech">
    <form wire:submit='create' class="space-y-6">
        <flux:text class="text-sm text-zinc-600">
            Tambahkan Spectech
        </flux:text>
        <flux:input wire:model='form.name' placeholder="Name Spectech..."></flux:input>
        <div class="grid grid-cols-3 gap-4">
            <flux:input wire:model='form.price' class="col-span-2" placeholder="Total Value..." type="number"></flux:input>
            <flux:input wire:model='form.quantity' placeholder="Quantity Total..." type="number"></flux:input>
        </div>
        <flux:input wire:model='form.notes' placeholder="Notes..."></flux:input>
        <flux:button type="submit" variant="primary" class="w-full">Tambahkan Spectech</flux:button>
    </form>
</flux:modal>
</div>

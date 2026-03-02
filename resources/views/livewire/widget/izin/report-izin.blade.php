<?php

use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public $approvedCount;
    public $rejectedCount;
    public $totalCount;

    public function mount()
    {
        $cacheKey = 'izin_widget_' . Auth::user()->username;

        $data = Cache::remember($cacheKey, now()->addHour(), function () {

            $response = Http::get(
                env('API_IZIN') . '/global/izin/dashboard/' . Auth::user()->username
            )->json();

            if (!$response['success']) {

                Toaster::error('Failed to fetch izin dashboard data from API.');

                \Log::error('Izin Dashboard API failed', [
                    'status' => $response['status'] ?? null,
                    'body'   => $response['message'] ?? 'No message',
                ]);

                return [
                    'approved' => 0,
                    'rejected' => 0,
                    'total' => 0,
                    'group' => [],
                ];
            }
            return [
                'approved' => $response['data']['approve_izin'],
                'rejected' => $response['data']['failed_izin'],
                'total' => $response['data']['all_izin'],
                'group' => $response['data']['group'],
                ];
        });

        $this->dispatch('widget-pengajuan', data: $data['group'] ?? []);
        $this->approvedCount = $data['approved'];
        $this->rejectedCount = $data['rejected'];
        $this->totalCount = $data['total'];
    }

    #[On('izinAdded')]
    public function refreshData()
    {
        Cache::forget('izin_widget_' . Auth::user()->username);
        $this->mount();
    }

}; ?>

<div>
    <div class="bg-white rounded-2xl border min-h-78 border-gray-200 p-6 space-y-6">
        {{-- Header --}}
        <div class="flex items-center gap-3">
            <flux:icon name="chart-bar" class="w-8 h-8 text-gray-700" />

            <div>
                <flux:heading size="lg" class="text-lg font-semibold text-gray-800">
                    Laporan Izin
                </flux:heading>
                <flux:description>
                    Ringkasan status pengajuan izin pengguna
                </flux:description>
            </div>
        </div>

        <div class="border-t"></div>

        {{-- Cards --}}
        <div class="grid md:grid-cols-3 gap-6">

            {{-- Disetujui --}}
            <div class="rounded-xl border border-gray-200 p-6 text-center space-y-2 hover:shadow-sm transition">
                <flux:icon name="check-circle" class="w-7 h-7 mx-auto text-green-500" />

                <p class="text-sm text-gray-500">Izin Disetujui</p>

                <h3 class="text-2xl font-semibold text-gray-900">
                    {{ $approvedCount ?? 0 }}
                </h3>
            </div>

            {{-- Ditolak --}}
            <div class="rounded-xl border border-gray-200 p-6 text-center space-y-2 hover:shadow-sm transition">
                <flux:icon name="x-circle" class="w-7 h-7 mx-auto text-red-500" />

                <p class="text-sm text-gray-500">Izin Ditolak</p>

                <h3 class="text-2xl font-semibold text-gray-900">
                    {{ $rejectedCount ?? 0 }}
                </h3>
            </div>

            {{-- Total --}}
            <div class="rounded-xl border border-gray-200 p-6 text-center space-y-2 hover:shadow-sm transition">
                <flux:icon name="document-text" class="w-7 h-7 mx-auto text-blue-500" />

                <p class="text-sm text-gray-500">Total Pengajuan</p>

                <h3 class="text-2xl font-semibold text-gray-900">
                    {{ $totalCount ?? 0 }}
                </h3>
            </div>

        </div>

    </div>
</div>

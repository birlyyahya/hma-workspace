<?php

use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;
use Illuminate\Support\Facades\Http;

new class extends Component
{
    public $project;

    public function mount(){

        $this->project = Cache::get('projects_data');

        if (!$this->project) {

            $response = Http::withToken(env('TOKEN_KEY'))
                ->get(env('API_PROJECT') . '/projects')->json();

                if ($response['status'] === 200) {

                $this->project = $response;

                Cache::put('projects_data', $this->project, now()->addHour());

            } else {
                // ❌ jangan cache error
                $this->project = $response;

                Toaster::error('Failed to fetch projects data from API.');
                // opsional: log biar tahu kenapa gagal
                logger()->error('Project API failed', [
                    'status' => $response['status'],
                    'body'   => $response['message'] ?? 'No message',
                ]);
            }
        }
    }

    public function placeholder(){
        return view('components.placeholder.ph_project');
    }

}
?>


<div>
    <div class="grid [grid-template-columns:repeat(auto-fit,minmax(270px,270px))] gap-y-10 justify-start">
        @forelse($this->project['data'] as $key => $item)

        <div class="card max-w-65 relative flex flex-col bg-white  border border-white rounded-2xl shadow-md hover:shadow-accent hover:shadow-md transition duration-400 space-y-4">
            <div class="card-header px-6 pt-6">
                <div class="flex">
                    <div class="absolute z-50 -top-6 left-6 rounded-full bg-red-400 w-20 h-20 text-center flex items-center justify-center">
                        <flux:icon name="folder" class="w-9 h-9 z-10 text-white m-2"></flux:icon>
                    </div>
                    <div class="flex ml-auto">
                        <flux:button icon="pencil-square" iconVariant="outline" class="py-4 px-6 rounded-r-none rounded-l-lg cursor-pointer" variant="outline" size="sm"></flux:button>
                        <flux:button icon="exclamation-circle" iconVariant="outline" class="py-4 px-6 rounded-l-none rounded-r-lg cursor-pointer" variant="outline" size="sm"></flux:button>
                    </div>
                </div>
            </div>
            <div class="card-body flex flex-col gap-3 mt-4 px-6">
                <flex:heading class="font-bold mb-2 text-sm h-15 overflow-auto">{{ $item['name'] }}</flex:heading>
                <div class="flex gap-3">
                    <flux:icon name="qr-code" class="w-5 h-5 text-zinc-500 mr-2"></flux:icon>
                    <flux:description>Code</flux:description>
                    <flux:description class="ml-auto font-bold text-zinc-800">{{ $item['code'] }}</flux:description>
                </div>
                <div class="flex gap-3">
                    <flux:icon name="folder" class="w-5 h-5 text-zinc-500 mr-2"></flux:icon>
                    <flux:description>Barang</flux:description>
                    <flux:description class="ml-auto font-bold text-zinc-800">6 Paket</flux:description>
                </div>
                <div class="flex gap-3">
                    <flux:icon name="document-check" class="w-5 h-5 text-zinc-500 mr-2"></flux:icon>
                    <flux:description>Contract</flux:description>
                    <flux:description class="ml-auto font-bold text-zinc-800">{{ \Carbon\Carbon::parse($item['contract_date'])->format('d M y') }}</flux:description>
                </div>
            </div>
            <div class="card-footer">
                <flux:separator class=""></flux:separator>
                <div class="p-6">
                    <p class="text-zinc-500 text-xs">{{ $item['company_name'] }}</p>
                </div>
            </div>
        </div>
        @empty
            <p class="text-zinc-400 flex items-center gap-2">No Projects Found.</p>
        @endforelse
    </div>
</div>

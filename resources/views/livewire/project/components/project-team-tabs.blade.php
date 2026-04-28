<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $id; //id project
    public $internal; //data tim internal
    public $internals; //data tim internal real
    public $timduk; // data timduk
    public $search; // filter pencarian

    public $selectedUser; // user yang akan diinvite (internal)
    public $nameTimduk; // nama timduk yang akan ditambahkan

    public $selectedTimduk; // timduk yang akan diupdate

    public function mount(){
        $this->dispatch('initSelect2');
        $ids = collect($this->internal)->pluck('user_id');
        $users = User::whereIn('id', $ids)->get();
        $this->internals = $this->internal;
        $this->internal = $users;
    }

    public function getSearchResultsProperty()
        {
            if (!$this->search) {
                return [
                    'internal' => $this->internal,
                    'timduk' => $this->timduk
                ];
            }

            $internal = collect($this->internal)->filter(function ($user) {
                return Str::contains(
                    Str::lower($user->name),
                    Str::lower($this->search)
                );
            });

            $timduk = collect($this->timduk)->filter(function ($name) {
                return Str::contains(
                    Str::lower($name),
                    Str::lower($this->search)
                );
            });

            return [
                'internal' => $internal->values(),
                'timduk' => $timduk->values()
            ];
        }

    public function getUserProperty(){
        return User::whereNotIn('role_id', [1,2])->get();;
    }

    public function inviteInternal(){
       if (collect($this->internal)->pluck('id')->contains($this->selectedUser)) {
            Toaster::error('User already invited');
            return;
        }

        $response = Http::post(env('API_PROJECT').'project-teams', [
            'project_id' => $this->id,
            'user_id' => $this->selectedUser
        ]);

        if($response['status'] === 201){
            $user = User::find($response['data']['user_id']);

            if ($user && !$this->internal->contains('id', $user->id)) {

                $this->internal->push($user);

                collect($this->internals)->push($response['data']);

                Flux::modal('invite-internal-modal')->close();

                $this->dispatch('projectLoad');

                Toaster::success('User invited successfully');

                return;
            }


        }

        Toaster::error('Failed to invite user');
    }

    public function removeInternal($id){
        $ids = collect($this->internals)->filter(function ($internals) use ($id) {
            return $internals['user_id'] === $id;
        })->pluck('id')->first();

        try {
            $response = Http::delete(env('API_PROJECT').'project-teams/'.$ids);

            if($response['status'] === 200){
                $this->internal = $this->internal->filter(function ($user) use ($id) {
                    return $user->id !== $id;
                });

                Toaster::success('User removed successfully');

                $this->dispatch('projectLoad');

                return;
            }

            Toaster::error(getErrorMessages($response['errors']));
        } catch (\Throwable $th) {
            \Log::error('Failed to remove user', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
                'system' => $th->getMessage(),
            ]);
        }
    }


    public function addTimduk(){

        try {
            $this->timduk = collect($this->timduk)->push($this->nameTimduk)->toArray();
            $this->nameTimduk = null;
            $response = Http::patch(env('API_PROJECT').'projects/'.$this->id, [
                'support_teams' => $this->timduk
            ]);

            if($response['status'] === 200){
                Toaster::success('Timduk added successfully');

                $this->dispatch('projectLoad');

                return;
            }

            Toaster::error(getErrorMessages($response['errors']));
        }catch (\Throwable $th) {
            Toaster::error('Failed to add timduk');
            \Log::error('Failed to add timduk', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
                'system' => $th->getMessage(),
            ]);
        }
    }

    public function removeTimduk($name){
        try {
            $this->timduk = collect($this->timduk)->filter(function ($timduk) use ($name) {
                return $timduk !== $name;
            })->toArray();
            $response = Http::patch(env('API_PROJECT').'projects/'.$this->id, [
                'support_teams' => $this->timduk
            ]);

            if($response['status'] === 200){
                Toaster::success('Timduk removed successfully');

                $this->dispatch('projectLoad');
                return;
            }

            Toaster::error(getErrorMessages($response['errors']));
        }catch (\Throwable $th) {
            Toaster::error('Failed to remove timduk');
            \Log::error('Failed to remove timduk', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
            ]);
        }
    }

}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-4 justify-between">
        <flux:input placeholder="Search by name" wire:model.live.defer.400ms="search" />
        <flux:button icon="funnel" variant="outline">Filter by</flux:button>

    </div>
    <div class="flex items-center gap-4">
        <flux:heading size="md">Team Internal</flux:heading>
        <flux:modal.trigger name="invite-internal-modal">
            <flux:button size="sm" icon="plus" variant="primary">Invite</flux:button>
        </flux:modal.trigger>
    </div>
    @if(count($this->searchResults['internal']) > 0)
    <div class="grid grid-cols-4 gap-4">
        @foreach ($this->searchResults['internal'] as $tim)
        <div class="max-w-sm bg-white border rounded-2xl p-6 shadow-sm">
            <!-- TOP -->
            <div class="flex justify-between items-start">
                <div class="flex gap-4 min-w-0">
                    <!-- Avatar -->
                    <flux:avatar circle name="{{ $tim['user_name'] }}" color="auto" color:seed="{{ $tim['user_name'] }}" size="md" />

                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold truncate">
                            {{ $tim['name'] }}
                        </h2>

                        <p class="text-gray-500 text-sm truncate">
                            {{ $tim['email'] }}
                        </p>
                    </div>
                </div>
            </div>
            <!-- LOCATION -->
            <div class="flex items-center gap-2 text-gray-600 mt-4">
                <div class="w-6 h-6 flex items-center justify-center bg-gray-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span class="text-sm">
                    Hanatekindo
                </span>
            </div>

            <!-- FOOTER -->
            <div class="flex items-center justify-between mt-4 pt-4 border-t">
                <flux:badge color="green" size="sm" icon="check-circle">{{ $tim['role']['name'] }}</flux:badge>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-gray-500 text-sm">
        Belum ada tim internal
    </div>
    @endif
    <div class="flex items-center gap-4">
        <flux:heading size="md">Team Pendukung</flux:heading>
        <flux:modal.trigger name="invite-ppk-modal">
            <flux:button size="sm" icon="plus" variant="primary">Invite</flux:button>
        </flux:modal.trigger>
    </div>
    @if(count($this->searchResults['timduk']) > 0)
    <div class="grid grid-cols-4 gap-4">
        @foreach ($this->searchResults['timduk'] as $tim)
        <div class="max-w-sm bg-white border rounded-2xl p-6 shadow-sm">
            <!-- TOP -->
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-4">
                    <!-- Avatar -->
                    <flux:avatar circle name="{{ $tim }}" color="auto" color:seed="{{ $tim }}" size="md" />
                    <div>
                        <h2 class="text-sm font-semibold">
                            {{ $tim }}
                        </h2>
                    </div>
                </div>
            </div>
            <!-- LOCATION -->
            <div class="flex items-center gap-2 text-gray-600 mt-4">
                <div class="w-6 h-6 flex items-center justify-center bg-gray-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span class="text-sm">
                    Kejaksaan
                </span>
            </div>

            <!-- FOOTER -->
            <div class="flex items-center justify-between mt-4 pt-4 border-t">
                <flux:badge color="green" size="sm" icon="check-circle">Admin</flux:badge>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-gray-500 text-sm">
        Belum ada tim pendukung
    </div>
    @endif

    <flux:modal name="invite-internal-modal" class="w-md">
        <div class="space-y-6">

            {{-- HEADER --}}
            <div>
                <flux:text class="text-sm font-semibold text-gray-800">
                    Add Internal
                </flux:text>
                <flux:description class="text-xs text-gray-500">
                    Tambahkan anggota tim internal ke dalam project
                </flux:description>
            </div>

            {{-- FORM INPUT --}}
            <div class="flex gap-2">
                <div wire:ignore class="w-full">
                    <select id="userTeam" class="select2 form-select">
                        @foreach ($this->user as $item)
                        <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <flux:button wire:click="inviteInternal" variant="primary" class="px-6">
                    Invite
                </flux:button>
            </div>

            {{-- LIST HEADER --}}
            <div class="flex items-center justify-between pt-2">
                <flux:text class="text-sm font-semibold text-gray-800">
                    Team Internal
                </flux:text>

                <span class="text-xs text-gray-400">
                    {{ count($this->searchResults['internal']) }} anggota
                </span>
            </div>

            {{-- LIST (SCROLLABLE) --}}
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">

                @forelse ($this->searchResults['internal'] as $tim)
                <div  wire:key="remove-internal-{{ $tim['id'] }}" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">

                    <div class="flex items-center gap-3 min-w-0">

                        {{-- AVATAR --}}
                        <flux:avatar circle name="{{ $tim['user_name'] }}" color="auto" color:seed="{{ $tim['user_name'] }}" size="sm" />

                        {{-- INFO --}}
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ $tim['name'] }}
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $tim['user_name'] }}
                            </p>
                        </div>

                    </div>

                    <flux:button wire:key="remove-internal-{{ $tim['id'] }}" size="xs" wire:click="removeInternal({{ $tim['id'] }})" variant="ghost">
                        Remove
                    </flux:button>

                </div>
                @empty
                <div class="text-center text-sm text-gray-400 py-6">
                    Belum ada anggota tim
                </div>
                @endforelse
            </div>

        </div>
    </flux:modal>

    {{-- TIMDUK --}}
    <flux:modal name="invite-ppk-modal" class="w-md">
        <div class="space-y-6">

            {{-- HEADER --}}
            <div>
                <flux:text class="text-sm font-semibold text-gray-800">
                    Add PPK
                </flux:text>
                <flux:description class="text-xs text-gray-500">
                    Tambahkan anggota PPK ke dalam project
                </flux:description>
            </div>

            {{-- FORM INPUT --}}
            <div class="flex gap-2">
                <flux:input wire:model.live="nameTimduk" placeholder="Name PPK..." class="w-full"></flux:input>

                <flux:button wire:click="addTimduk" variant="primary" class="px-6">
                    Invite
                </flux:button>
            </div>

            {{-- LIST HEADER --}}
            <div class="flex items-center justify-between pt-2">
                <flux:text class="text-sm font-semibold text-gray-800">
                    Team Pendukung
                </flux:text>

                <span class="text-xs text-gray-400">
                    {{ count($this->searchResults['timduk']) }} anggota
                </span>
            </div>

            {{-- LIST (SCROLLABLE) --}}
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">

                @forelse ($this->searchResults['timduk'] as $tim)
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">

                    <div class="flex items-center gap-3 min-w-0">

                        {{-- AVATAR --}}
                        <flux:avatar circle name="{{ $tim }}" color="auto" color:seed="{{ $tim }}" size="sm" />

                        {{-- INFO --}}
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ $tim }}
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                Kejaksaan
                            </p>
                        </div>

                    </div>

                    <flux:button class="cursor-pointer" wire:click="removeTimduk('{{ $tim }}')" size="xs" variant="ghost">
                        Remove
                    </flux:button>

                </div>
                @empty
                <div class="text-center text-sm text-gray-400 py-6">
                    Belum ada anggota tim
                </div>
                @endforelse
            </div>

        </div>
    </flux:modal>

</div>

@script
<script>
    const userteam = () => {
        const el = $('#userTeam');
        el.select2({
            dropdownParent: $('dialog[data-modal="invite-internal-modal"]')
            , placeholder: "Select a team"
            , width: '100%'
            , allowClear: true
        , });

        el.on('change', function() {
            @this.set('selectedUser', $(this).val());
        });
    };

    Livewire.on('initSelect2', () => {
        setTimeout(userteam, 0);
    });

    window.addEventListener('init-select2', () => {
        setTimeout(userteam, 0);
    });

</script>
@endscript

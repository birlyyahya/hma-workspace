<?php

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public $internal;
    public $timduk;
    public $search;

    public function mount(){

        $ids = collect($this->internal)->pluck('user_id');

        $users = User::whereIn('id', $ids)->get();

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
                <div class="flex gap-4">
                    <!-- Avatar -->
                    <flux:avatar circle name="{{ $tim ['user_name']}}" color="auto" color:seed="{{ $tim ['user_name']}}" size="md" />
                    <div>
                        <h2 class="text-sm font-semibold">
                            {{ $tim ['name']}}
                        </h2>
                        <p class="text-gray-500 text-sm">
                            {{ $tim ['email']}}
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
                <div class="flex gap-3">
                    <flux:icon.ellipsis-vertical class="w-5 h-5 cursor-pointer hover:text-gray-700" />
                </div>
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
                <div class="flex gap-4">
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
                <div class="flex gap-3">
                    <flux:icon.ellipsis-vertical class="w-5 h-5 cursor-pointer hover:text-gray-700" />
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-gray-500 text-sm">
        Belum ada tim pendukung
    </div>
    @endif

    <flux:modal name="invite-internal-modal" class="w-md overflow-visible">
        <form class="space-y-6">

            {{-- HEADER --}}
            <div>
                <flux:text class="text-sm font-semibold text-gray-800">
                    Invite User
                </flux:text>
                <flux:description class="text-xs text-gray-500">
                    Tambahkan anggota tim internal ke dalam project
                </flux:description>
            </div>

            {{-- FORM INPUT --}}
            <div class="flex gap-2">

                <div wire:ignore class="flex-1">
                    <select id="userTeam" class="select2 form-select " placeholder="select a team">
                        @foreach ($this->user as $item)
                        <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <flux:button type="submit" variant="primary" class="px-6">
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

            {{-- LIST --}}
            <div class="space-y-2">

                @forelse ($this->searchResults['internal'] as $tim)
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">

                    <div class="flex items-center gap-3">

                        {{-- AVATAR --}}
                        <flux:avatar circle name="{{ $tim['user_name'] }}" color="auto" color:seed="{{ $tim['user_name'] }}" size="sm" />

                        {{-- INFO --}}
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $tim['name'] }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $tim['user_name'] }}
                            </p>
                        </div>

                    </div>

                    {{-- ACTION (optional) --}}
                    <flux:button size="xs" variant="ghost">
                        Remove
                    </flux:button>

                </div>
                @empty
                <div class="text-center text-sm text-gray-400 py-6">
                    Belum ada anggota tim
                </div>
                @endforelse

            </div>

        </form>
    </flux:modal>
    <flux:modal name="invite-ppk-modal">
        <form class="space-y-6">
            <flux:text class="text-sm text-zinc-600">
                Invite User
            </flux:text>
            <flux:input wire:model='' placeholder="Name User..."></flux:input>
            <flux:button type="submit" variant="primary" class="w-full">Invite</flux:button>
        </form>
    </flux:modal>

</div>

@script
<script>
    const initSelect2Team = () => {
        const el = $('#userTeam');
        el.select2({
            dropdownParent: $('dialog[data-modal="invite-internal-modal"]')
            , width: '100%'
            , placeholder: "Select a team"
            , allowClear: true
        , });

        // el.on('change', function() {
        //     @this.set('form.team', $(this).val());
        // });
    };

    Livewire.on('initSelect2', () => {
        setTimeout(initSelect2Team, 0);
    });

    window.addEventListener('init-select2', () => {
        setTimeout(initSelect2Team, 0);
    });

</script>
@endscript



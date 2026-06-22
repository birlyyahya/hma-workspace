<?php

use App\Livewire\Forms\ActivityForm;
use App\Models\User;
use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public ActivityForm $form;

    public $tasks = [];
    public $page = 1;
    public $perPage = 5;
    public $search;
    public $start_date;
    public $end_date;
    public $taskStatus;
    public $loading =true;
    public $projectData;
    public $projectSelected;
    public $spectech;

    public ?int $pendingDeleteId = null;

    public function mount(){

        try {
            $response = Http::get(config('services.api_izin'). '/global/dar/list?page='.$this->page.'&perPage='.$this->perPage.'&user_id='.Auth::user()->id.'&search='.$this->search.'&start_date='.$this->start_date.'&end_date='.$this->end_date.'')->json();

            $activities = collect($response['data']);

            $userIds = $activities->pluck('user_id')->unique();

            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            $activities = $activities->map(function ($activity) use ($users) {

                $activity['user'] = $users[$activity['user_id']] ?? null;

                return $activity;
            });

            // $this->dispatch('darList', $activities->toArray());
            $this->tasks = $response;
            foreach ($this->tasks['data'] as $task) {
                $this->taskStatus[$task['id']] = $task['status'];
            }
            $this->loading= false;
            // dd($response);
            return $response;
        }catch (\Throwable $th) {
            Toaster::error('Server PM Error, silahkan coba lagi atau menghubungi tim IT');
            $this->loading= false;
            return [];
        }
    }

    public function goToPage($page)
    {
        $this->page = $page;
        $this->fetch();
    }
    public function searchData()
    {
        $this->page = 1;
        $this->fetch();
    }

    public function resetDate(){
        $this->start_date = '';
        $this->end_date = '';
        $this->page = 1;
        $this->fetch();
    }

    public function updatedEndDate()
    {
        $this->page = 1;
        $this->fetch();
    }
    public function updatedStartDate()
    {
        $this->page = 1;
        $this->fetch();
    }

    public function fetch()
    {
        $this->loading = true;
        $response = Http::get(config('services.api_izin'). '/global/dar/list?page='.$this->page.'&perPage='.$this->perPage.'&search='.$this->search.'&start_date='.$this->start_date.'&end_date='.$this->end_date.'&user_id='.Auth::user()->id)->json();
        $this->tasks = $response;
        $this->loading = false;
    }

    public function getUserProperty(){
        return User::whereNotIn('role_id', [1,2])->get();;
    }

    public function createActivity(){
        $response = $this->form->store($this->projectSelected);

        if ($response->successful() && ($response->json('success') ?? false)) {
            app(DarCache::class)->flush();
            $this->reset('form');
            $this->dispatch('updatedTimeline');
            Toaster::success('Create Activity successfully');
        } else {
            Toaster::error('Create Activity failed');
            \Log::error('Activity API failed', [
                'status' => $response->status(),
                'body'   => $response->json('message') ?? $response->body(),
            ]);
        }
    }

    public function updatedTaskStatus($value, $id)
    {
        $response = Http::post(
            config('services.api_izin').'global/dar/activity/'.$id.'/status',
            [
                '_method' => 'PUT',
                'status' => $value
            ]
        );

        if($response['success']) {
            app(DarCache::class)->flush();
            Toaster::success('Update Activity successfully');
            $this->dispatch('updatedTimeline');
            return $this->fetch();
        }

        Toaster::error('Update Activity failed');
        \Log::error('Activity API failed', [
            'status' => $response['success'],
            'body'   => $response['message'] ?? 'No message',
        ]);

    }

    public function projectData(){
        try {
            $this->projectData = app(ProjectCache::class)->leaderProjects(Auth::user()->id);
            return $this->projectData;
        }catch(\Exception $e){
            return [];
        }
    }

    public function updatedProjectSelected(){
        $this->spectech = collect($this->projectData)->where('id', $this->projectSelected)->first()['specktech'];
    }

    public function confirmDelete($id){
        dd($id);
        if (!Auth::user()->hasPermission('dar.delete')) {
            Toaster::error('You do not have permission to delete this activity.');
            return;
        }
        $this->pendingDeleteId = (int) $id;
        \Flux\Flux::modal('delete-task-modal')->show();
    }

    public function deleteTask(){
        if(! $this->pendingDeleteId){
            return;
        }

        $response = Http::delete(config('services.api_izin').'global/dar/activity/'.$this->pendingDeleteId);
        if($response['success']){
            app(DarCache::class)->flush();
            $this->pendingDeleteId = null;
            \Flux\Flux::modal('delete-task-modal')->close();
            Toaster::success('Delete Activity successfully');
            $this->dispatch('updatedTimeline');
            return $this->fetch();
        }
        Toaster::error('Delete Activity failed');
        \Log::error('Activity API failed', [
            'status' => $response['success'],
            'body'   => $response['message'] ?? 'No message',
        ]);
    }


    public function placeholder(){
        return view('components.placeholder.ph_dar_table');
    }
}; ?>

<div>
    <div class="relative bg-white rounded-lg">
        <div class="flex items-center justify-between px-6 py-4 bg-white">
            <div class="flex items-center gap-3">
                <flux:icon name="bars-3" class="w-5 h-5 text-gray-400" />
                <h2 class="text-base font-semibold">Task Assign</h2>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <flux:input icon="magnifying-glass" wire:model.defer="search" placeholder="Search Name.." wire:keydown.enter="searchData" size="sm"></flux:input>
                <flux:dropdown position="top" align="start">
                    <flux:button icon="calendar" iconClasses="w-4 h-4" size="sm" class="font-light" variant="ghost"></flux:button>
                    {{-- <flux:icon name="calendar" class="w-4 h-4 cursor-pointer" /> --}}
                    <flux:menu>
                        <flux:menu.item disabled>
                            <flux:input label="Start Date" wire:model.live='start_date' type="date" wire:model.live='start_date'></flux:input>
                        </flux:menu.item>
                        <flux:menu.item disabled>
                            <flux:input label="End Date" wire:model.live='end_date' type="date" wire:model.live='end_date'></flux:input>
                        </flux:menu.item>
                        <flux:menu.item>
                            <flux:button class="w-full" size="sm" wire:click="resetDate">Reset</flux:button>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <flux:modal.trigger name="create-activity">
                    <flux:button icon="plus" iconClasses="w-4 h-4 cursor-pointer font-normal" iconVariant="outline" class="px-2 cursor-pointer font-light
                    " size="sm" variant="ghost"></flux:button>
                </flux:modal.trigger>
                <flux:icon name="ellipsis-horizontal" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
            </div>
        </div>
        <div class="overflow-visible">
            <table class="min-w-[900px] md:min-w-full text-sm text-left text-gray-600 ">
                <thead class="bg-zinc-50 border shadow-none text-xs uppercase text-gray-500 ">
                    <tr>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">No</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Project Name</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Due Date</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Kegiatan</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 md:px-6 whitespace-nowrap">Team</th>
                        <th class="px-3 py-3 md:px-6 text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @if($this->tasks)
                    @foreach ($this->tasks['data'] as $task)
                    <tr wire:key='{{ $task['id'] }}' class="border-b border-gray-200 hover:bg-gray-50 transition">
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ ($tasks['current_page'] - 1) * $tasks['per_page'] + $loop->iteration }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $task['activity'] }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ Carbon::parse($task['end_date'])->locale('id')->translatedFormat('l, d M Y') }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $task['project_id'] ? 'Project' : 'Non Project' }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                            <div wire:loading wire:target="taskStatus.{{ $task['id'] }}" class="flex items-center h-full py-2 px-3 w-full border rounded-lg bg-gray-50 text-gray-500 text-sm animate-pulse">
                                Updating...
                            </div>
                            <flux:select wire:loading.remove wire:key="status-{{ $task['id'] }}" wire:loading.attr="disabled" wire:model.live="taskStatus.{{ $task['id'] }}" placeholder="Status">
                                <flux:select.option value="1">Pending</flux:select.option>
                                <flux:select.option value="2">Hold</flux:select.option>
                                <flux:select.option value="3">In Progress</flux:select.option>
                                <flux:select.option value="4">Completed</flux:select.option>
                            </flux:select>
                        </td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                            @if(isset($task['team']))
                            <flux:avatar.group>
                                @foreach ($task['team'] as $user)
                                @if ($loop->index < 3) <flux:tooltip content="{{ $user['name'] }}">
                                    <flux:avatar circle name="{{ $user['name'] }}" color="auto" color:seed="{{ $user['id'] }}" size="sm" />
                                    </flux:tooltip>
                                    @endif
                                    @endforeach
                                    @if (count($task['team']) > 3)
                                    <flux:tooltip content="{{ join(', ', array_map(fn ($user) => $user['name'], $task['team'])) }}">
                                        <flux:avatar circle size="sm">
                                            +{{ count($user) - 2 }}
                                        </flux:avatar>
                                    </flux:tooltip>
                                    @endif
                            </flux:avatar.group>
                            @else
                            <flux:tooltip content="{{ Auth::user()->name }}">
                                <flux:avatar circle name="{{ Auth::user()->name }}" color="auto" color:seed="{{ Auth::user()->name}}" size="sm" />
                            </flux:tooltip>
                            @endif
                        </td>
                        <td class="px-3 py-3 md:px-6 justify-end flex whitespace-nowrap gap-2">
                            <flux:icon name="pencil-square" class="w-5 h-5 cursor-pointer hover:text-gray-700" />
                            <div x-data="{ open: false }" class="relative inline-block">

                                <!-- Icon -->
                                <flux:icon name="ellipsis-horizontal" class="w-5 h-5 cursor-pointer hover:text-gray-700" @click="open = !open" />

                                <!-- Dropdown -->
                                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full mt-2 w-40 bg-white border rounded-lg shadow-md z-9999">
                                    <div class="flex flex-col">
                                        <button class="w-full  cursor-pointer text-left px-4 py-2 text-sm hover:bg-gray-100">
                                            Detail
                                        </button>
                                        <button class="w-full flex justify-between cursor-pointer text-left px-4 py-2 text-sm hover:bg-gray-100">
                                            Share Link <flux:icon name="link" class="w-4 h-4"/>
                                        </button>
                                        <button wire:click="confirmDelete({{ $task['id'] }})" @click="open = false" class="w-full cursor-pointer text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="6" class="px-3 py-3 md:px-6 text-center text-gray-400">
                            Error Server Down! silahkan coba lagi nanti atau menghubungi tim IT
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <div wire:loading.flex wire:target="goToPage,searchData,resetDate,updatedStartDate,updatedEndDate" class="absolute inset-0 z-20
                flex items-center justify-center
                bg-white/50 backdrop-blur-sm">
                <div class="flex flex-col items-center gap-2">
                    <div class="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
                    <span class="text-sm text-gray-600">Loading data...</span>
                </div>
            </div>
        </div>

        @if($this->tasks)
        <nav class="flex flex-col md:flex-row md:items-center md:justify-between p-4 gap-4" aria-label="Table navigation">

            <!-- Info -->
            <span class="text-sm text-gray-600 text-center md:text-left">
                Showing
                <span class="font-semibold text-gray-900">
                    {{ $this->tasks['from'] }}-{{ $this->tasks['to'] }}
                </span>
                of
                <span class="font-semibold text-gray-900">
                    {{ $this->tasks['total'] }}
                </span>
            </span>

            <!-- Pagination -->
            <ul class="flex flex-wrap md:flex-nowrap items-center justify-center md:justify-start gap-1 md:gap-0 md:-space-x-px text-sm">

                @php
                $current = $this->tasks['current_page'];
                $last = $this->tasks['last_page'];
                $start = max($current - 2, 1);
                $end = min($current + 2, $last);
                @endphp

                {{-- Previous --}}
                <li>
                    <button wire:click="goToPage({{ $current - 1 }})" @disabled(!$this->tasks['prev_page_url'])
                        class="px-3 h-9 flex items-center justify-center
                        border border-gray-300 bg-white
                        rounded-l-lg text-gray-700
                        hover:bg-gray-100 disabled:opacity-50">
                        Previous
                    </button>
                </li>

                {{-- First Page --}}
                @if ($start > 1)
                <li>
                    <button wire:click="goToPage(1)" class="w-9 h-9 flex items-center justify-center
                border border-gray-300 bg-white
                text-gray-700 hover:bg-gray-100">
                        1
                    </button>
                </li>

                @if ($start > 2)
                <li>
                    <span class="w-9 h-9 flex items-center justify-center
                        border border-gray-300 bg-white text-gray-500">
                        ...
                    </span>
                </li>
                @endif
                @endif

                {{-- Middle Pages --}}
                @for ($i = $start; $i <= $end; $i++) <li>
                    <button wire:click="goToPage({{ $i }})" class="w-9 h-9 flex items-center justify-center
                border border-gray-300
                {{ $i == $current
                    ? 'bg-gray-600 text-white font-semibold'
                    : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                        {{ $i }}
                    </button>
                    </li>
                    @endfor

                    {{-- Last Page --}}
                    @if ($end < $last) @if ($end < $last - 1) <li>
                        <span class="w-9 h-9 flex items-center justify-center
                        border border-gray-300 bg-white text-gray-500">
                            ...
                        </span>
                        </li>
                        @endif

                        <li>
                            <button wire:click="goToPage({{ $last }})" class="w-9 h-9 flex items-center justify-center
                border border-gray-300 bg-white
                text-gray-700 hover:bg-gray-100">
                                {{ $last }}
                            </button>
                        </li>
                        @endif

                        {{-- Next --}}
                        <li>
                            <button wire:click="goToPage({{ $current + 1 }})" @disabled(!$this->tasks['next_page_url'])
                                class="px-3 h-9 flex items-center justify-center
                                border border-gray-300 bg-white
                                rounded-r-lg text-gray-700
                                hover:bg-gray-100 disabled:opacity-50">
                                Next
                            </button>
                        </li>
            </ul>
        </nav>
        @endif

    </div>

    @if(!$this->loading)
    <flux:modal x-data="{ isProject: false }" name="create-activity" class="w-xl overflow-visible">
        <form wire:submit='createActivity' class="space-y-6">
            <div>
                <flux:heading size="lg">Create Activity</flux:heading>
            </div>
            <flux:input wire:model='form.activity' placeholder="Name Activity" />
            @error('form.activity')
            <flux:error message="{{ $message }}" />
            @enderror
            <flux:checkbox wire:model='form.isproject' x-model="isProject" label="Activity project?" />
            <div x-show="isProject" x-transition>
                <flux:select wire:model.live='projectSelected' placeholder="Choose project...">
                    @foreach ($this->projectData() as $item)
                    <flux:select.option value="{{ $item['id'] }}">{{ $item['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            @error('form.project_id')
            <flux:error message="{{ $message }}" />
            @enderror
            <div x-show="isProject" x-transition>
                <flux:select wire:model='form.spectech_id' placeholder="Choose Spectech...">
                    @foreach ($this->spectech ?? [] as $item)
                    <flux:select.option value="{{ $item['id'] }}">{{ $item['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            @error('form.spectech_id')
            <flux:error message="{{ $message }}" />
            @enderror
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:input wire:model='form.start_date' label="Tanggal Mulai" type="datetime-local" />
                </div>
                <div>
                    <flux:input wire:model='form.end_date' label="Tanggal Berakhir" type="datetime-local" />
                </div>
            </div>
            <div>
                <flux:label>Team</flux:label>
                <x-search-select
                    model="form.team"
                    multiple
                    :avatar="true"
                    :options="$this->user->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all()"
                    placeholder="Pilih anggota tim..."
                    search-placeholder="Cari anggota tim..."
                />
            </div>
            @error('form.team')
            <flux:error message="{{ $message }}" />
            @enderror
            <flux:select wire:model='form.status' placeholder="Choose status...">
                <flux:select.option value="1">Pending</flux:select.option>
                <flux:select.option value="2">Hold</flux:select.option>
                <flux:select.option value="3">In Progress</flux:select.option>
                <flux:select.option value="4">Completed</flux:select.option>
            </flux:select>
            @error('form.status')
            <flux:error message="{{ $message }}" />
            @enderror
            <div class="flex justify-end">
                <flux:button type="submit" color="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif

    {{-- Delete confirmation modal --}}
    <x-confirm-modal name="delete-task-modal" confirm="deleteTask" title="Hapus aktivitas ini?"
        description="Aktivitas DAR akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan." />
</div>

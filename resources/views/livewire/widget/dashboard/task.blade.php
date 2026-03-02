<?php

use Flux\Flux;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Tasks;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;
use App\Models\TaskAssignments;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\View\Components\Task;
use App\Notifications\TaskAssignedNotification;

new class extends Component
{
    public $taskName;
    public $taskDueDate;
    public $taskPriority = 'low';
    public $taskDescription;

    // Query pencarian autocomplete user
    public $query;
    // Hasil pencarian user untuk autocomplete
    public $results;

    // Daftar user yang diassign ke task
    public $assign = [];

    // ID task yang akan direject
    public $rejectId;

    // Alasan reject
    #[Validate('required|string|max:500', onUpdate: false)]
    public $reasonRejected;

    public function mount(){
        $this->taskDueDate = now()->format('Y-m-d');
    }

   public function getTaskProperty()
    {
        return Tasks::where(function ($query) {

        // ✅ Task yang saya buat
        $query->where('assigned_by', auth()->id())

        // ATAU

        // ✅ Task yang saya di-assign & tidak reject
        ->orWhereHas('assignments', function ($q) {
            $q->where('user_id', auth()->id())
              ->where('status', '!=', 'rejected');
        });

    })->get();
    }

    public function saveTask()
    {

        $this->validate([
            'taskName' => ['required', 'string', 'max:255'],
            'taskDueDate' => ['required', 'date'],
            'taskPriority' => ['required', 'in:low,medium,high'],
            'assign' => ['required', 'array', 'min:1'],
            'taskDescription' => ['nullable', 'string', 'max:500'],
        ]);
        $assignees = array_map(fn ($assigned) => $assigned['id'], $this->assign);
        try {
            DB::transaction(function () use ($assignees) {
                $task = Tasks::create([
                    'name'        => $this->taskName,
                    'description' => $this->taskDescription ?? '',
                    'due_date'    => $this->taskDueDate,
                    'priority'    => $this->taskPriority ?? 'low',
                    'status'      => 'assigned',
                    'assigned_by' => auth()->id(),
                ]);

                collect($assignees)->each(function ($userId) use ($task) {
                    TaskAssignments::create([
                        'tasks_id' => $task->id,
                        'user_id' => $userId,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $users = User::whereIn('id', $assignees)->get();

                foreach ($users as $user) {
                    $user->notify(new TaskAssignedNotification($task));
                }
            });
            Toaster::success('Task added successfully!');

            // Reset form fields
            $this->reset(['taskName', 'taskDueDate', 'taskPriority', 'assign', 'query', 'results']);
            Flux::modal('add-task')->close();
        }catch(\Exception $e){
            DB::rollBack();
            Toaster::error('Failed to add task: ' . $e->getMessage());
            \Log::error('Failed to add task: ' . $e->getMessage());
        }
    }

    public function updatedQuery(){
        $this->results = User::where('name', 'like', '%' . $this->query . '%')->select('name','id')->get()->toArray();
        if (empty($this->query)) {
            $this->results = '';
        }
    }

    public function assigned($name): void
    {
        if (in_array($name, $this->assign, true)) {
        $this->assign = array_values(
            array_filter($this->assign, fn ($item) => $item !== $name)
        );
        } else {
            $this->assign[] = $name;
        }
    }

    public function openNotification(){
        return auth()->user()->unreadNotifications->markAsRead();
    }

    public function modalConfirm($id){
        Flux::modal('reject-task')->show();
        $this->rejectId = $id;
    }

    public function closeModal(){
        Flux::modal('reject-task')->close();
        $this->rejectId = null;
    }

    public function rejectTask(){
        $this->validateOnly('reasonRejected');
        try {
            TaskAssignments::where('id', $this->rejectId)->update([
                'status' => 'rejected',
                'responded_at' => now(),
                'reject_reason' => $this->reasonRejected
            ]);
            Toaster::success('Task rejected successfully!');
            Flux::modal('reject-task')->close();

        }catch(\Exception $e){
            DB::rollBack();
            Toaster::error('Failed to reject task: ' . $e->getMessage());
            \Log::error('Failed to reject task: ' . $e->getMessage());
        }
    }

    public function acceptTask($id){
        try {
            TaskAssignments::where('id', $id)->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
            Toaster::success('Task accepted successfully!');

        }catch(\Exception $e){
            DB::rollBack();
            Toaster::error('Failed to accept task: ' . $e->getMessage());
            \Log::error('Failed to accept task: ' . $e->getMessage());
        }
    }

    public function taskProgress($id){
        try {
            Tasks::where('id', $id)->update([
                'status' => 'in_progress',
                'updated_by' => auth()->id(),
            ]);
            Toaster::success('Task status updated to In Progress!');

        }catch(\Exception $e){
            DB::rollBack();
            Toaster::error('Failed to update task status: ' . $e->getMessage());
            \Log::error('Failed to update task status: ' . $e->getMessage());
        }
    }
    public function taskCompleted($id){
        try {
            Tasks::where('id', $id)->update([
                'status' => 'completed',
                'updated_by' => auth()->id(),
            ]);
            Toaster::success('Task marked as Completed!');

        }catch(\Exception $e){
            DB::rollBack();
            Toaster::error('Failed to mark task as Completed: ' . $e->getMessage());
            \Log::error('Failed to mark task as Completed: ' . $e->getMessage());
        }
    }
    public function checkNotification()
    {
        $hasUnread = auth()->user()
            ->unreadNotifications()
            ->exists(); // cek ada data atau tidak

        if ($hasUnread) {
            $this->dispatch('play-notification-sound');
        }
    }
};

?>



<div>
    <div class="rounded-xl bg-white shadow-sm border border-gray-200  p-6 flex flex-col">
        <div class="card-heading flex items-center justify-between mb-8">
            <div class="flex items-center">
                <flux:heading size="base" class="text-[1rem] font-medium">Today's Task</flux:heading>
            </div>
            <div class="flex gap-4">
                <div class="relative">
                    @if(auth()->user()->unreadNotifications->isNotEmpty())
                    <span class="absolute top-1 right-1 flex size-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-red-500"></span>
                    </span>
                    @endif
                    <flux:dropdown>
                        <flux:tooltip content="Notifications">
                            <flux:button variant="ghost" size="sm" icon="bell" class="relative" iconVariant="outline" x-on:click="$wire.openNotification()" />
                        </flux:tooltip>
                        <flux:menu>
                            <flux:menu.item>Notification</flux:menu.item>
                            <flux:menu.separator />
                            <div class="h-78 overflow-auto">
                                @if(empty(auth()->user()->notifications))
                                <flux:menu.item>
                                    <flux:description class="text-xs font-light">No new notifications</flux:description>
                                </flux:menu.item>
                                @endif
                                @foreach (auth()->user()->notifications as $notification)
                                <flux:menu.item class="mb-2">
                                    <flux:avatar name="{{ $notification->data['assigned_by'] }}" size="sm" class="me-2"></flux:avatar>
                                    <div class="flex flex-col">
                                        <div class="flex justify-between items-center">
                                            <flux:description class="text-xs font-bold">{{ $notification->data['assigned_by'] }}</flux:description>
                                            <flux:description class="text-xs text-zinc-500 ms-2">{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</flux:description>
                                        </div>
                                        <flux:description class="text-xs font-light">{{ $notification->data['message'] }}</flux:description>
                                    </div>
                                </flux:menu.item>
                                @endforeach
                            </div>
                        </flux:menu>
                    </flux:dropdown>
                </div>
                <flux:modal.trigger name="history-task">
                    <flux:tooltip content="History Tasks">
                        <flux:button size="sm" variant="ghost" icon="bookmark" iconVariant="outline"></flux:button>
                    </flux:tooltip>
                </flux:modal.trigger>
            </div>
        </div>
        {{-- Add Task Button --}}
        <flux:modal.trigger name="add-task">
            <button class=" w-full border border-dashed border-gray-300 rounded-xl py-2 text-sm text-gray-500 hover:bg-gray-50">
                + Add New Task
            </button>
        </flux:modal.trigger>

        {{-- Task List --}}
        <div class="mt-4 space-y-4">
            @forelse(collect($this->task)->whereNotIn('status', 'completed') as $task)
            <div class="border border-gray-100 rounded-xl p-4 space-y-3 shadow-sm">
                {{-- Top Badges --}}
                <div class="flex justify-between items-center gap-2">
                    <div class="flex gap-2">
                        @if($task->assignments->pluck('status')[0] === 'rejected')
                        <flux:badge icon="x-mark" iconVariant="outline" class="text-xs">Rejected</flux:badge>
                        @elseif($task->assignments->pluck('status')[0] === 'accepted')
                        <flux:badge icon="check-badge" iconVariant="outline" class="text-xs">Accepted</flux:badge>
                        @else
                        <flux:badge :icon="match($task['status']){
                            'progress' => 'star',
                            'completed' => 'check-badge',
                            'assigned' => 'pencil-square'
                        }" iconVariant="outline" class="text-xs">{{ ucwords($task['status']) }}</flux:badge>
                        @endif
                        <flux:badge icon="flag" :color="match($task['priority']){
                            'high' => 'red',
                            'medium' => 'yellow',
                            'low' => 'green',
                        }" iconVariant="outline" class="text-xs">{{ ucwords($task['priority']) }}</flux:badge>
                    </div>
                    @if($task->assignments->pluck('status')[0] === 'pending' && $task->assignments->pluck('user_id')[0] === auth()->id())
                    <div class="flex gap-2">
                        <flux:button size="xs" icon="hand-thumb-down" class="cursor-pointer w-4 h-4  transition duration-300 hover:scale-130" iconVariant="outline" wire:click="modalConfirm({{ $task->assignments->pluck('id')[0] }})" />
                        <flux:button size="xs" icon="hand-thumb-up" iconVariant="outline" class="cursor-pointer w-4 h-4 hover:scale-130 transition duration-300" wire:click="acceptTask({{ $task->assignments->pluck('id')[0] }})" />
                    </div>
                    @elseif ($task->assignments->pluck('status')[0] === 'accepted')
                    <flux:tooltip content="Completed">
                        <flux:button size="xs" icon="check" class="cursor-pointer w-4 h-4  transition duration-300 hover:scale-130" iconVariant="outline" wire:click="taskCompleted({{ $task['id'] }})" />
                    </flux:tooltip>
                    @endif
                </div>

                {{-- Title --}}
                <h4 class="font-semibold text-gray-900 leading-snug">
                    {{ $task['name'] }}
                </h4>

                {{-- Description --}}
                <p class="text-sm text-gray-500">
                    {{ $task['description'] }}
                </p>
                <flux:separator />

                {{-- Footer --}}
                <div class="flex items-center justify-between pt-2">

                    {{-- Avatars --}}
                    <div class="flex -space-x-2">
                        <flux:avatar.group>
                            @foreach ($task->assignments as $assigned)
                            @if ($loop->index < 3) <flux:tooltip content="{{ $assigned->user->name }}">
                                <flux:avatar size="xs" name="{{ $assigned->user->name }}" />
                                </flux:tooltip>
                                @endif

                                @endforeach

                                @if (count($task->assignments) > 3)
                                <flux:tooltip content="{{ $task->assignments->pluck('user.name')->implode(', ')}}">
                                    <flux:avatar size="xs">
                                        +{{ count($task->assignments) - 3 }}
                                    </flux:avatar>
                                </flux:tooltip>
                                @endif
                        </flux:avatar.group>
                    </div>

                    {{-- Meta --}}
                    <div class="flex items-center gap-4 text-xs text-gray-500">

                        <div class="flex items-center gap-1  font-medium">
                            <flux:icon name="calendar-days" class="w-4 h-4" />
                            {{ $task->due_date->locale('id')->calendar(null, [
                                'sameDay'  => '[Hari ini]',
                                'nextDay'  => '[Besok]',
                                'lastDay'  => '[Kemarin]',
                                'sameElse' => 'D MMM YYYY',
                            ]) }}
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-500">No task found</p>
            @endforelse
        </div>
    </div>

    {{-- Modal Add Task --}}
    <flux:modal name="add-task" class="md:w-96 overflow-visible">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Task</flux:heading>
                <flux:text class="mt-2">Add or Assign a new task to your project</flux:text>
            </div>
            <flux:input label="Name" placeholder="Task name" wire:model="taskName" />
            <flux:textarea placeholder="Wrote details here..." wire:model="taskDescription" />
            <div class="grid grid-cols-2 gap-4 w-full">
                <flux:input label="Due Date" type="date" class="w-full" wire:model="taskDueDate" />
                <flux:select label="Priority" class="w-full" wire:model="taskPriority">
                    <flux:select.option value="high">High</flux:select.option>
                    <flux:select.option value="medium">Medium</flux:select.option>
                    <flux:select.option value="low">Low</flux:select.option>
                </flux:select>
            </div>
            <div wire:poll.10s='checkNotification' class="flex gap-4 items-center">
                <div class="relative w-full max-w-45">
                    <flux:input iconTrailing="magnifying-glass" autocomplete="off" wire:model.live='query' placeholder="Assign to " class="w-full" />
                    @if(!empty($results))
                    <ul class="absolute z-50 max-h-30 overflow-auto p-0 w-full bg-white border border-gray-200 rounded-md shadow-lg">
                        @foreach($results as $result)
                        <li class="px-4 py-2 text-xs hover:bg-gray-100 cursor-pointer flex items-center gap-2" wire:click="assigned(@js($result))">
                            <flux:avatar name="{{ $result['name'] }}" size="xs" />{{ $result['name'] }}</li>
                        @endforeach
                    </ul>
                    @endif
                </div>
                <flux:avatar.group>
                    @foreach ($assign as $assigned)

                    @if ($loop->index
                    < 3) <flux:avatar name="{{ $assigned['name'] }}" />
                    @endif

                    @endforeach

                    @if (count($assign) > 3)
                    <flux:tooltip content="{{ join(', ', array_map(fn ($assigned) => $assigned['name'], $assign)) }}">
                        <flux:avatar>
                            +{{ count($assign) - 3 }}
                        </flux:avatar>
                    </flux:tooltip>
                    @endif
                </flux:avatar.group>
            </div>
            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="saveTask" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Reject Task --}}
    <flux:modal name="reject-task">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Are you sure?</flux:heading>
                <flux:text class="mt-2">Do you really want to reject this task? This process cannot be undone.</flux:text>
                <flux:input wire:model="reasonRejected" placeholder="Type your reason here..." class="w-full mt-4" wire:model="reasonRejected" />
            </div>
            <div class="flex gap-4 justify-end">
                <flux:button variant="outline" wire:click="closeModal">Cancel</flux:button>
                <flux:button variant="danger" wire:click="rejectTask">Reject</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal History --}}
    <flux:modal name="history-task" flyout variant="floating" class="md:w-lg transition-all duration-500 overflow-auto">
        <div class="space-y-6 mb-5 border-b pb-4">
            <flux:heading size="lg">History Tasks</flux:heading>
            <flux:subheading>View the history of tasks in your project.</flux:subheading>
        </div>
        @php
        $grouped = $this->task
        ->where('status', 'completed')
        ->sortByDesc('updated_at')
        ->groupBy(fn($task) => Carbon::parse($task->updated_at)->format('Y-m-d'));
        @endphp

        <div class="space-y-6 overflow-auto">

            @foreach ($grouped as $date => $tasks)
            {{-- HEADER TANGGAL --}}
            <p class="text-sm text-zinc-400">
                {{ Carbon::parse($date)->translatedFormat('d F Y') }}
            </p>

            {{-- TASKS DI TANGGAL ITU --}}
            <div class="space-y-3">
                @foreach ($tasks as $item)
                <div class="flex gap-4 items-center justify-between border-b border-gray-200 pb-3">
                    <div class="gap-4 flex items-center w-full">
                        <flux:tooltip content="Assigned by {{ $item->creator->name }}">
                            <flux:avatar color="yellow" name="{{ $item->creator->name }}" size="xs" />
                        </flux:tooltip>

                        <div class="flex justify-between items-center w-full">
                            <div class="flex flex-col">
                                <p class="line-clamp-2 text-sm">{{ $item->name }}</p>
                                <p class="line-clamp-2 text-xs text-gray-400">{{ $item->description }}</p>
                            </div>
                            @if($item->assignments->where('user_id', auth()->id())->pluck('status')->first() === 'rejected')
                            <flux:badge color="gray" class="text-xs">Rejected</flux:badge>
                            @else
                            <flux:badge color="green" class="text-xs">{{ ucwords($item->status) }}</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @endforeach
        </div>

    </flux:modal>
</div>

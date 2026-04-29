<?php

use App\Livewire\Forms\ActivityForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public ActivityForm $form;

    public array $tasks = [];
    public array $projectData = [];
    public array $users = [];
    public $projectSelected = null;
    public array $timelines = [];
    public string $search = '';

    public bool $loading = true;

    public ?int $pendingDeleteId = null;
    public string $pendingDeleteName = '';

    public function mount(): void
    {
        $this->users = User::whereNotIn('role_id', [1, 2])->get()->toArray();

        try {
            $apiProject = rtrim(config('services.api_project'), '/');
            $this->projectData = Http::get($apiProject.'/projects/search?project_leader_id='.Auth::id())->json() ?? [];
        } catch (\Throwable $e) {
            $this->projectData = [];
        }

        $this->fetchTasks();
        $this->resetForm();

        $this->dispatch('initSelect2');
    }

    public function updatedProjectSelected(): void
    {
        collect($this->projectData['data'] ?? [])->firstWhere('id', $this->projectSelected);
        $this->timelines = Http::get(config('services.api_project').'timelines/search?project_id='.$this->projectSelected.'&user_id='.Auth::id())->json()['data'] ?? [];
        $this->form->project_id = $this->projectSelected;
    }

    #[On('updatedCardTaskDar')]
    public function fetchTasks(): void
    {
        $this->loading = true;

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');

            if(Auth::user()->role_id < 3){
                $response = Http::timeout(120)->retry(3, 200)->get(
                    $apiIzin.'/global/dar/list?limit=50000&search='.$this->search
                )->json();
            } else {
            $response = Http::timeout(120)->retry(3, 200)->get(
                $apiIzin.'/global/dar/list?team_user='.Auth::id().'&limit=50000&search='.$this->search
            )->json();
            }
            $this->tasks = $response['data'] ?? [];
        } catch (\Throwable $e) {
            $this->tasks = [];
            Toaster::error('Server DAR Error, silahkan coba lagi atau menghubungi tim IT');
            Log::error('DAR list API failed', ['message' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    public function projectData(): array
    {
        return $this->projectData['data'] ?? [];
    }

    public function teamUser($users): array
    {
        $userMap = collect($this->users)->keyBy('id');

        return collect($users)
            ->map(fn ($id) => $userMap[$id]['name'] ?? null)
            ->filter()
            ->all();
    }

    public function createActivity(): void
    {
        try {
            $response = $this->form->store($this->projectSelected);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Toaster::error('Server Error saat membuat task');
            Log::error('DAR create exception', ['message' => $e->getMessage()]);

            return;
        }

        $success = is_array($response) ? ($response['success'] ?? false) : ($response['success'] ?? false);

        if (! $success) {
            $message = is_array($response) ? ($response['message'] ?? 'Create Task failed') : 'Create Task failed';
            Toaster::error($message);
            Log::error('DAR create API failed', [
                'message' => $message,
                'errors' => $response['errors'] ?? null,
            ]);

            return;
        }

        Toaster::success('Create Activity successfully');

        $this->resetForm();
        $this->projectSelected = null;
        $this->timelines = [];

        Flux::modals()->close('create-task');

        $this->dispatch('resetSelect2');
        $this->dispatch('updatedCardTaskDar');
        $this->dispatch('updatedTimeline');

        $this->fetchTasks();
    }

    private function resetForm(): void
    {
        $this->form->resetForm();
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_task_dar');
    }

    public function confirmDeleteTask(int $id, string $name = ''): void
    {
        $this->pendingDeleteId = $id;
        $this->pendingDeleteName = $name;
        Flux::modal('delete-task')->show();
    }

    public function cancelDeleteTask(): void
    {
        $this->pendingDeleteId = null;
        $this->pendingDeleteName = '';
        Flux::modal('delete-task')->close();
    }

    public function deleteTask()
    {
        $id = $this->pendingDeleteId;

        if (empty($id)) {
            Toaster::error('Invalid task id');
            return;
        }

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');
            $response = Http::delete($apiIzin . '/global/dar/activity/' . $id);

            $status = method_exists($response, 'status') ? $response->status() : null;
            $body = method_exists($response, 'json') ? $response->json() : null;

            if ($status === 200 || ($body['success'] ?? false)) {
                Toaster::success('Task berhasil dihapus');
                $this->pendingDeleteId = null;
                $this->pendingDeleteName = '';
                Flux::modal('delete-task')->close();
                $this->dispatch('updatedTimeline');
                $this->fetchTasks();
                return;
            }

            Toaster::error('Menghapus task gagal');
            \Log::error('DAR delete API failed', [
                'status' => $status,
                'body' => method_exists($response, 'body') ? $response->body() : $body,
            ]);
        } catch (\Throwable $e) {
            Toaster::error('Server Error saat menghapus task');
            \Log::error('DAR delete API exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }

}; ?>


<div>
    <style>
        [x-cloak] {
            display: none !important;
        }

    </style>

    <section>
        {{-- Basecamp-ish section header --}}
        <header class="px-5 py-4">
            <div class="flex items-center gap-3">
                <flux:modal.trigger name="create-task">
                    <flux:button icon="plus-circle" iconClasses="size-6" variant="outline">
                        Tambah Tugas
                    </flux:button>
                </flux:modal.trigger>

                <div class="flex flex-1 items-center gap-4">
                    <div class="h-px flex-1 bg-slate-200/70"></div>
                    <h2 class="text-lg font-semibold tracking-tight text-slate-800">Tugas</h2>
                    <div class="h-px flex-1 bg-slate-200/70"></div>
                </div>

                <flux:input x-on:keydown.enter="$wire.fetchTasks()" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search task..." class="w-full md:w-64" />
            </div>
        </header>

        <div class="px-5 pb-5">
            @if($loading)
            <div class="rounded-2xl bg-white p-6 text-sm text-slate-600 ring-1 ring-slate-200/70 shadow-sm">
                Loading tasks...
            </div>
            @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.remove wire:target="fetchTasks">
                @forelse(collect($tasks)->sortBy('status') as $task)
                @php
                $status = $task['status'] ?? 'Draft';
                $taskId = $task['id'] ?? null;
                $taskUrl = $taskId ? route('dar.dar-show', $taskId) : '#';
                $statusColor = match ($status) {
                1 => 'bg-slate-50 text-slate-700 ring-slate-200', // pending
                2 => 'bg-amber-50 text-amber-800 ring-amber-200', // hold
                3 => 'bg-blue-50 text-blue-700 ring-blue-200', // in progress
                4 => 'bg-emerald-50 text-emerald-800 ring-emerald-200', // completed
                default => 'bg-slate-50 text-slate-700 ring-slate-200',
                };

                $assignees = $this->teamUser(collect($task['team_user'])->pluck('user_id')) ?? [];

                if (empty($assignees)) {
                $assignees = [Auth::user()->name];
                }
                @endphp

                <article x-data="{ menuOpen: false }" class="group relative  rounded-2xl hover:z-50 bg-white ring-1 ring-slate-200/70 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <a href="{{ $taskUrl }}" wire:navigate class="absolute inset-0 z-0 rounded-2xl" aria-label="Open task"></a>

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 relative z-10">
                                <a href="{{ $taskUrl }}" class="text-base font-semibold leading-snug text-slate-900">
                                    {{ ucwords($task['activity']) ?? 'Untitled task' }}
                                </a>
                                <a href="{{ $taskUrl }}" class="mt-1 text-sm leading-relaxed text-slate-600 line-clamp-1">
                                    {!! $task['description'] ?? 'No description provided.' !!}
                                </a>
                            </div>

                            {{-- Ellipsis menu --}}
                            <div class="relative shrink-0 z-20" @keydown.escape.window="menuOpen = false">
                                <button type="button" @click="menuOpen = !menuOpen" class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Task menu">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                        <circle cx="5" cy="12" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="19" cy="12" r="1.6" />
                                    </svg>
                                </button>

                                <div x-cloak x-show="menuOpen" @click.away="menuOpen = false" x-transition.origin.top.right class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200/70">
                                    <a href="{{ $taskUrl }}" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                        Open
                                    </a>
                                    <button type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                        Edit
                                    </button>
                                    <button type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                        Mark as done
                                    </button>
                                    <div class="h-px bg-slate-200/70"></div>
                                    <button type="button" @click="menuOpen = false" wire:click="confirmDeleteTask({{ $taskId }}, @js(ucwords($task['activity'] ?? 'Untitled task')))" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 {{ $statusColor }}">
                                {{ $status === 1 ? 'Pending' : ($status === 2 ? 'Hold' : ($status === 3 ? 'In Progress' : ($status === 4 ? 'Completed' : 'Draft'))) }}
                            </span>

                            @if(!empty($task['end_date']))
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M7 3v3" />
                                    <path d="M17 3v3" />
                                    <path d="M3.5 9h17" />
                                    <path d="M5.5 6h13A2 2 0 0 1 20.5 8v12A2 2 0 0 1 18.5 22h-13A2 2 0 0 1 3.5 20V8A2 2 0 0 1 5.5 6Z" />
                                </svg>
                                {{ \Carbon\Carbon::parse($task['end_date'])->format('d M') }}
                            </span>
                            @endif
                        </div>
                    </div>

                    <footer class="relative z-10 flex items-center justify-between gap-3 border-t border-slate-200/70 px-5 py-4">
                        <div class="flex -space-x-2">
                            @php
                            $maxVisible = 5;
                            $visible = array_slice($assignees, 0, $maxVisible);
                            $remaining = count($assignees) - $maxVisible;
                            @endphp

                            <div class="flex -space-x-2">
                                @foreach($visible as $assignee)
                                <flux:avatar name="{{ $assignee }}" circle class="size-7 text-xs ring-1 ring-white" />
                                @endforeach

                                @if($remaining > 0)
                                <flux:avatar circle class="size-7 text-xs ring-1 ring-white bg-slate-100 text-slate-600">
                                    +{{ $remaining }}
                                </flux:avatar>
                                @endif
                            </div>
                        </div>

                        <span class="text-xs font-medium text-slate-500">
                            Dimulai {{ \Carbon\Carbon::parse($task['start_date'])->subHours(2)->diffForHumans() }}
                        </span>
                    </footer>
                </article>
                @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                    Belum ada tugas. Klik <span class="font-semibold">Tambah Tugas</span> untuk membuat yang baru.
                </div>
                @endforelse
            </div>
            @endif
            <div class="w-1/3" wire:loading wire:target="fetchTasks">
                <div class="rounded-2xl bg-white ring-1 ring-slate-200/70 shadow-sm animate-pulse">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">

                            {{-- Title + description --}}
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-3/4 rounded bg-slate-200"></div>
                                <div class="h-3 w-full rounded bg-slate-200"></div>
                                <div class="h-3 w-5/6 rounded bg-slate-200"></div>
                            </div>

                            {{-- Menu button --}}
                            <div class="h-9 w-9 rounded-full bg-slate-200"></div>
                        </div>

                        {{-- Badges --}}
                        <div class="mt-4 flex gap-2">
                            <div class="h-5 w-16 rounded-full bg-slate-200"></div>
                            <div class="h-5 w-20 rounded-full bg-slate-200"></div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200/70 px-5 py-4">

                        {{-- Avatars --}}
                        <div class="flex -space-x-2">
                            <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                            <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                            <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                        </div>

                        {{-- Date --}}
                        <div class="h-3 w-24 rounded bg-slate-200"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <flux:modal name="delete-task" class="min-w-md" :dismissible="false">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18" />
                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">Hapus tugas ini?</flux:heading>
                    <flux:text class="mt-1 text-sm text-slate-600">
                        Tugas <span class="font-semibold text-slate-900">"{{ $pendingDeleteName ?: 'Untitled task' }}"</span>
                        akan dihapus secara permanen beserta seluruh aktivitas terkait. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelDeleteTask">Batal</flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteTask"
                    wire:loading.attr="disabled"
                    wire:target="deleteTask"
                >
                    <span wire:loading.remove wire:target="deleteTask">Hapus tugas</span>
                    <span wire:loading wire:target="deleteTask">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal x-data="{ isProject: false }" name="create-task" class="min-w-2xl overflow-visible">
        <form wire:submit='createActivity' class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Tugas</flux:heading>
            </div>
            <flux:input wire:model='form.activity' placeholder="Nama Tugas" />
            @error('form.activity')
            <flux:error message="{{ $message }}" />
            @enderror
            <flux:textarea wire:model='form.description' placeholder="Jelaskan lebih detail..."></flux:textarea>
            @error('form.description')
            <flux:error message="{{ $message }}" />
            @enderror
            <flux:checkbox wire:model='form.isproject' x-model="isProject" label="Kegiatan Project?" />
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
                @if(!empty($this->timelines))
                <flux:select wire:model='form.timelines_id' placeholder="Choose timelines...">
                    @foreach ($this->timelines ?? [] as $item)
                    <flux:select.option value="{{ $item['id'] }}">{{ $item['title'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                @else
                 <div wire:loading wire:target="updatedProjectSelected, projectSelected" class="text-sm text-slate-500">
                    Loading timeline...
                </div>
                <div wire:loading.remove wire:target="updatedProjectSelected, projectSelected" class="rounded bg-yellow-50 p-4 text-sm text-yellow-700 ring-1 ring-yellow-200">
                    Tidak ada timeline tersedia untuk project ini. Silakan buat timeline terlebih dahulu di menu project.
                </div>
                @endif
            </div>
            @error('form.timelines_id')
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
            <div wire:ignore>
                <select id="teamUser" multiple="multiple" class="select2 form-select" placeholder="Pilih team untuk tugas ini">
                    @foreach ($this->users as $item)
                    <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @error('form.team_user')
            <flux:error message="{{ $message }}" />
            @enderror
            <flux:select wire:model='form.status' placeholder="Choose status...">
                <flux:select.option value="1">Draft</flux:select.option>
                <flux:select.option value="2">Hold</flux:select.option>
                <flux:select.option value="3">In Progress</flux:select.option>
                <flux:select.option value="4">Completed</flux:select.option>
            </flux:select>
            @error('form.status')
            <flux:error message="{{ $message }}" />
            @enderror
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Buat tugas</flux:button>
            </div>

        </form>
    </flux:modal>
</div>

{{-- script --}}
@script
<script>
    const initTeamUserSelect2 = () => {
        const el = $('#teamUser');
        if (!el.length) return;

        if (el.hasClass('select2-hidden-accessible')) {
            el.select2('destroy');
        }

        el.select2({
            dropdownParent: $('dialog[data-modal="create-task"]'),
            width: '100%',
            placeholder: 'Pilih team untuk tugas ini',
            allowClear: true,
        });

        el.off('change.teamUser').on('change.teamUser', function () {
            const values = ($(this).val() || []).map(v => parseInt(v, 10)).filter(v => !isNaN(v));
            $wire.set('form.team_user', values);
        });
    };

    const resetTeamUserSelect2 = () => {
        const el = $('#teamUser');
        if (!el.length) return;
        el.val(null).trigger('change');
    };

    Livewire.on('initSelect2', () => {
        setTimeout(initTeamUserSelect2, 0);
    });

    Livewire.on('resetSelect2', () => {
        setTimeout(resetTeamUserSelect2, 0);
    });
</script>
@endscript

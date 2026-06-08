<?php


use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public $date;
    public $id; // project id
    public $user_id;

    public $timelines = [];
    public $activities = [];
    public $months = [];
    public $selectedMonth; // format: Y-m

    // Timeline CRUD UI state (in-memory only, no backend)
    public bool $showTimelineModal = false;
    public bool $showDeleteModal = false;
    public bool $showManageModal = false;
    public ?int $editingTimelineId = null;
    public ?int $deletingTimelineId = null;

    public string $form_title = '';
    public string $form_start_date = '';
    public string $form_end_date = '';

    public function placeholder()
    {
        return view('components.placeholder.ph_project_timeline_tabs');
    }

    #[On('timelineLoad')]
    public function mount(){

        // Dummy timelines
        $response = Http::timeout(300)->retry(3)->get(config('services.api_project').'timelines/search?user_id='.$this->user_id.'&project_id='.$this->id)->json();

        if($response['status'] !== 200) {
            Toaster::error('Failed to load timelines. Please refresh the page.');

            \Log::error('Timeline loading failed', [
                'response_status' => $response['status'],
                'response_message' => $response['message'] ?? 'No message',
                'response_errors' => $response['errors'],
            ]);
            return;
        }

        $this->timelines = $response['data'] ?? [];

        $response_dar = Http::get(config('services.api_izin'). '/global/dar/list?team_user='.$this->user_id.'&project_id='.$this->id)->json();

        if(!$response_dar['success']) {
            Toaster::error('Failed to load activities. Please refresh the page.');

            \Log::error('Activities loading failed', [
                'response_status' => $response_dar['success'],
                'response_message' => $response_dar['message'] ?? 'No message',
                'response_errors' => $response_dar['errors'],
            ]);
            return;
        }

        $this->activities = $response_dar['data'] ?? [];


        $start = collect($this->timelines)->min('start_date');
        $end = collect($this->timelines)->max('end_date');

        $period = CarbonPeriod::create(
            Carbon::parse($start)->startOfMonth(),
            '1 month',
            Carbon::parse($end)->endOfMonth()
        );

        $this->months = collect($period)->map(function ($date) {
            return [
                'label' => $date->locale('id')->translatedFormat('M Y'),
                'value' => $date->format('Y-m'),
            ];
        })->values()->toArray();

        $current = Carbon::now()->format('Y-m');
        $this->selectedMonth = collect($this->months)->contains('value', $current)
            ? $current
            : ($this->months[0]['value'] ?? null);
    }

    public function selectMonth(string $month): void
    {
        $this->selectedMonth = $month;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActivitiesForSelectedMonth(): array
    {
        if (! $this->selectedMonth) {
            return [];
        }

        $monthStart = Carbon::parse($this->selectedMonth.'-01')->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        return collect($this->activities)
            ->filter(function ($a) use ($monthStart, $monthEnd) {
                $s = Carbon::parse($a['start_date']);
                $e = Carbon::parse($a['end_date']);

                return $s <= $monthEnd && $e >= $monthStart;
            })
            ->sortBy('start_date')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTimelinesForMonth(string $month): array
    {
        $monthStart = Carbon::parse($month.'-01')->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        return collect($this->timelines)
            ->filter(function ($t) use ($monthStart, $monthEnd) {
                $s = Carbon::parse($t['start_date']);
                $e = Carbon::parse($t['end_date']);
                return $s <= $monthEnd && $e >= $monthStart;
            })
            ->values()
            ->all();
    }

    public function userTeams($data){
        return User::whereIn('id', collect($data)->pluck('user_id'))->get()->keyBy('id');
    }

    // ---------------- Timeline CRUD (in-memory) ----------------

    public function openCreateTimeline(): void
    {
        $this->resetValidation();
        $this->editingTimelineId = null;
        $this->form_title = '';
        $this->form_start_date = Carbon::now()->format('Y-m-d');
        $this->form_end_date = Carbon::now()->addMonth()->format('Y-m-d');
        $this->showTimelineModal = true;
    }

    public function openEditTimeline(int $id): void
    {
        $this->resetValidation();
        $tl = collect($this->timelines)->firstWhere('id', $id);
        if (! $tl) {
            return;
        }

        $this->editingTimelineId = $id;
        $this->form_title = $tl['title'];
        $this->form_start_date = $tl['start_date'];
        $this->form_end_date = $tl['end_date'];
        $this->showTimelineModal = true;
        $this->showManageModal = false;
    }

    public function saveTimeline(): void
    {
        $this->validate([
            'form_title' => ['required', 'string', 'min:2', 'max:80'],
            'form_start_date' => ['required', 'date'],
            'form_end_date' => ['required', 'date', 'after_or_equal:form_start_date'],
        ], [], [
            'form_title' => 'nama timeline',
            'form_start_date' => 'tanggal mulai',
            'form_end_date' => 'tanggal akhir',
        ]);

        if ($this->editingTimelineId) {

            $response = Http::patch(
                config('services.api_project') . 'timelines/' . $this->editingTimelineId,
                [
                    'user_id' => Auth::user()->id,
                    'project_id' => $this->id,
                    'title' => $this->form_title,
                    'start_date' => $this->form_start_date,
                    'end_date' => $this->form_end_date,
                ]
            );

            if ($response->failed()) {
                Toaster::error('Failed to update timeline.');

                \Log::error('Timeline update failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $this->timelines = collect($this->timelines)->map(function ($tl) {
                if ($tl['id'] === $this->editingTimelineId) {
                    $tl['title'] = $this->form_title;
                    $tl['start_date'] = $this->form_start_date;
                    $tl['end_date'] = $this->form_end_date;
                }
                return $tl;
            })->values()->all();

            Toaster::success('Timeline updated successfully!');

        } else {
            $response = Http::post(config('services.api_project').'timelines', [
                'user_id' => Auth::user()->id,
                'project_id' => $this->id,
                'title' => $this->form_title,
                'start_date' => $this->form_start_date,
                'end_date' => $this->form_end_date,
            ]);

            if ($response['status'] !== 201) {
                Toaster::error('Failed to create timeline. Please try again.');
                \Log::error('Timeline creation failed', [
                    'response_status' => $response['status'],
                    'response_message' => $response['message'] ?? 'No message',
                    'response_errors' => $response['errors'],
                ]);
                return;
            }
            $this->timelines[] = [
                'id' => $response['data']['id'],
                'title' => $this->form_title,
                'start_date' => $this->form_start_date,
                'end_date' => $this->form_end_date,
            ];

            Toaster::success('Timeline created successfully!');
        }

        $this->recomputeMonths();
        $this->dispatch('timelinesUpdated');
        $this->closeTimelineModal();
    }

    public function closeTimelineModal(): void
    {
        $this->showTimelineModal = false;
        $this->editingTimelineId = null;
        $this->form_title = '';
        $this->form_start_date = '';
        $this->form_end_date = '';
    }

    public function confirmDeleteTimeline(int $id): void
    {
        $this->deletingTimelineId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteTimeline(): void
    {
        if (! $this->deletingTimelineId) {
            return;
        }

        $response = Http::delete(config('services.api_project').'timelines/'.$this->deletingTimelineId);

        if($response['status'] !== 200) {
            Toaster::error('Failed to delete timeline. Please try again.');

            \Log::error('Timeline deletion failed', [
                'response_status' => $response['status'],
                'response_message' => $response['message'] ?? 'No message',
                'response_errors' => $response['errors'],
            ]);
            return;
        }

        $this->timelines = collect($this->timelines)
        ->reject(fn ($tl) => $tl['id'] === $this->deletingTimelineId)
        ->values()
        ->all();

        $this->deletingTimelineId = null;
        $this->showDeleteModal = false;

        Toaster::success('Timeline deleted successfully!');
        $this->recomputeMonths();
        $this->dispatch('timelinesUpdated');
    }

    public function openManageTimeline(): void
    {
        $this->showManageModal = true;
    }

    private function recomputeMonths(): void
    {
        if (empty($this->timelines)) {
            $this->months = [];
            $this->selectedMonth = null;
            return;
        }

        $start = collect($this->timelines)->min('start_date');
        $end = collect($this->timelines)->max('end_date');

        $period = CarbonPeriod::create(
            Carbon::parse($start)->startOfMonth(),
            '1 month',
            Carbon::parse($end)->endOfMonth()
        );

        $this->months = collect($period)->map(fn ($date) => [
            'label' => $date->locale('id')->translatedFormat('M Y'),
            'value' => $date->format('Y-m'),
        ])->values()->toArray();

        if (! collect($this->months)->contains('value', $this->selectedMonth)) {
            $current = Carbon::now()->format('Y-m');
            $this->selectedMonth = collect($this->months)->contains('value', $current)
                ? $current
                : ($this->months[0]['value'] ?? null);
        }
    }

}

 ?>

<div class="space-y-6">
    <div class="bg-white border rounded-2xl overflow-hidden">

        {{-- HEADER --}}
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div class="flex items-center gap-3">
                <flux:icon name="calendar-days" class="w-5 h-5 text-gray-400" />
                <h2 class="text-base font-semibold">Project Timeline</h2>
            </div>

            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" icon="list-bullet" wire:click="openManageTimeline">
                    Kelola
                </flux:button>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateTimeline">
                    Timeline
                </flux:button>
            </div>
        </div>

        {{-- MONTH TABS --}}
        <div class="border-b bg-gray-50">
            <div class="overflow-x-auto">
                <div class="flex items-center gap-1 px-4 py-2 min-w-max">
                    @foreach($months as $month)
                    @php
                    $isActive = $selectedMonth === $month['value'];
                    $monthStart = Carbon::parse($month['value'].'-01')->startOfMonth();
                    $monthEnd = (clone $monthStart)->endOfMonth();
                    $count = collect($activities)
                        ->filter(function ($a) use ($monthStart, $monthEnd) {
                            $s = Carbon::parse($a['start_date']);
                            $e = Carbon::parse($a['end_date']);

                            return $s <= $monthEnd && $e >= $monthStart;
                        })
                        ->count();
                    @endphp
                    <button
                        type="button"
                        wire:click="selectMonth('{{ $month['value'] }}')"
                        wire:key="month-{{ $month['value'] }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap
                        {{ $isActive ? 'bg-zinc-900 text-white shadow' : 'text-gray-600 hover:bg-gray-200' }}">
                        <span>{{ $month['label'] }}</span>
                        @if($count > 0)
                        <span class="text-xs px-1.5 py-0.5 rounded-full
                            {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                            {{ $count }}
                        </span>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ACTIVE MONTH'S TIMELINES --}}
        @if($selectedMonth)
        @php
        $monthTimelines = $this->getTimelinesForMonth($selectedMonth);
        $monthActivities = $this->getActivitiesForSelectedMonth();
        $statusMap = [
            1 => ['label' => 'Open', 'class' => 'bg-blue-100 text-blue-700', 'dot' => 'bg-blue-500'],
            2 => ['label' => 'Pending', 'class' => 'bg-amber-100 text-amber-700', 'dot' => 'bg-amber-500'],
            3 => ['label' => 'Cancelled', 'class' => 'bg-red-100 text-red-700', 'dot' => 'bg-red-500'],
            4 => ['label' => 'Closed', 'class' => 'bg-emerald-100 text-emerald-700', 'dot' => 'bg-emerald-500'],
        ];
        @endphp

        @if(count($monthTimelines))
        <div class="px-6 py-4 border-b bg-white">
            <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Timeline aktif</p>
            <div class="flex flex-wrap gap-2">
                @foreach($monthTimelines as $tl)
                <div wire:key="tl-chip-{{ $tl['id'] }}"
                     class="group inline-flex items-center gap-2 pl-3 pr-1 py-1 rounded-full bg-gray-50 border text-xs text-gray-700 hover:border-gray-300 transition">
                    <flux:icon name="flag" class="size-3 text-gray-400" />
                    <span class="font-medium">{{ $tl['title'] }}</span>
                    <span class="text-gray-400">
                        {{ Carbon::parse($tl['start_date'])->locale('id')->translatedFormat('d M') }}
                        –
                        {{ Carbon::parse($tl['end_date'])->locale('id')->translatedFormat('d M Y') }}
                    </span>
                    <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                        <flux:tooltip content="Edit">
                            <button type="button" wire:click="openEditTimeline({{ $tl['id'] }})"
                                class="p-1 rounded-full hover:bg-gray-200 text-gray-500">
                                <flux:icon name="pencil-square" class="size-3.5" />
                            </button>
                        </flux:tooltip>
                        <flux:tooltip content="Hapus">
                            <button type="button" wire:click="confirmDeleteTimeline({{ $tl['id'] }})"
                                class="p-1 rounded-full hover:bg-red-100 text-gray-500 hover:text-red-600">
                                <flux:icon name="trash" class="size-3.5" />
                            </button>
                        </flux:tooltip>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ACTIVITY LIST (vertical timeline) --}}
        <div class="px-6 py-6 bg-white">
            @if(count($monthActivities))
            <ol class="relative border-l border-gray-200 ml-3 space-y-6">
                @foreach($monthActivities as $activity)
                @php
                $tl = collect($timelines)->firstWhere('id', $activity['project_category_id']);
                $status = $statusMap[(int) ($activity['status'] ?? 0)] ?? $statusMap[1];
                @endphp
                <li wire:key="act-{{ $activity['id'] }}" class="ml-6">
                    <span class="absolute -left-[7px] flex items-center justify-center w-3.5 h-3.5 rounded-full ring-4 ring-white {{ $status['dot'] }}"></span>

                    <div class="flex flex-wrap items-start justify-between gap-3 mb-1">
                        <div class="flex items-center gap-2">
                            <time class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                {{ Carbon::parse($activity['start_date'])->locale('id')->translatedFormat('D, d M Y') }}
                            </time>
                            @if($tl)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-600">
                                {{ $tl['title'] }}
                            </span>
                            @endif
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full {{ $status['class'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>
                            {{ $status['label'] }}
                        </span>
                    </div>

                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50 hover:bg-white hover:shadow-sm transition">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $activity['activity'] }}</h3>
                        @if(!empty($activity['description']))
                        <p class="mt-1 text-xs text-gray-500 leading-relaxed">{{ strip_tags($activity['description']) }}</p>
                        @endif
                        <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="clock" class="size-3.5" />
                                {{ Carbon::parse($activity['start_date'])->format('H:i') }}
                                –
                                {{ Carbon::parse($activity['end_date'])->format('H:i') }}
                            </span>
                            @if(Carbon::parse($activity['start_date'])->format('Y-m-d') !== Carbon::parse($activity['end_date'])->format('Y-m-d'))
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="calendar" class="size-3.5" />
                                s/d {{ Carbon::parse($activity['end_date'])->locale('id')->translatedFormat('d M Y') }}
                            </span>
                            @endif
                        </div>
                    </div>
                </li>
                @endforeach
            </ol>
            @else
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <flux:icon name="calendar" class="w-10 h-10 text-gray-300 mb-3" />
                <p class="text-sm font-medium text-gray-600">Belum ada aktivitas</p>
                <p class="text-xs text-gray-400 mt-1">Tidak ada aktivitas yang tercatat pada bulan ini.</p>
            </div>
            @endif
        </div>
        @endif

    </div>

    {{-- ============== MODAL: CREATE / EDIT TIMELINE ============== --}}
    <flux:modal wire:model.self="showTimelineModal" class="md:w-120">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingTimelineId ? 'Edit Timeline' : 'Tambah Timeline' }}
                </flux:heading>
                <flux:text class="mt-1 text-sm text-gray-500">
                    {{ $editingTimelineId
                        ? 'Perbarui nama atau rentang tanggal timeline.'
                        : 'Buat timeline baru untuk mengelompokkan aktivitas.' }}
                </flux:text>
            </div>

            <form wire:submit="saveTimeline" class="space-y-4">
                <flux:field>
                    <flux:label>Nama Timeline</flux:label>
                    <flux:input wire:model="form_title" placeholder="cth: Planning, Design, Development" autofocus />
                    <flux:error name="form_title" />
                </flux:field>

                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Tanggal Mulai</flux:label>
                        <flux:input type="date" wire:model="form_start_date" />
                        <flux:error name="form_start_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Tanggal Akhir</flux:label>
                        <flux:input type="date" wire:model="form_end_date" />
                        <flux:error name="form_end_date" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closeTimelineModal">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ $editingTimelineId ? 'Simpan Perubahan' : 'Tambah Timeline' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- ============== MODAL: DELETE CONFIRMATION ============== --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-105">
        @php
        $deletingTl = collect($timelines)->firstWhere('id', $deletingTimelineId);
        $linkedActivities = $deletingTimelineId
            ? collect($activities)->where('project_category_id', $deletingTimelineId)->count()
            : 0;
        @endphp

        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-full bg-red-100">
                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="md">Hapus Timeline?</flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-500">
                        Timeline
                        <span class="font-semibold text-gray-700">"{{ $deletingTl['title'] ?? '' }}"</span>
                        akan dihapus.
                        @if($linkedActivities > 0)
                        Ada <span class="font-semibold text-red-600">{{ $linkedActivities }} aktivitas</span> yang
                        terkait — relasi timeline-nya akan terputus.
                        @endif
                        Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">
                    Batal
                </flux:button>
                <flux:button type="button" variant="danger" icon="trash" wire:click="deleteTimeline">
                    Hapus
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ============== MODAL: MANAGE ALL TIMELINES ============== --}}
    <flux:modal wire:model.self="showManageModal" class="md:w-160">
        <div class="space-y-5">
            <div class="flex justify-between gap-10 mr-10">
                <div>
                    <flux:heading size="lg">Kelola Timeline</flux:heading>
                    <flux:text class="text-sm text-gray-500">
                        Daftar semua timeline pada project ini.
                    </flux:text>
                </div>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateTimeline">
                    Tambah
                </flux:button>
            </div>

            @if(count($timelines))
            <div class="border rounded-xl divide-y max-h-100 overflow-y-auto">
                @foreach($timelines as $tl)
                <div wire:key="manage-tl-{{ $tl['id'] }}"
                    class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="p-2 rounded-lg bg-zinc-100">
                            <flux:icon name="flag" class="w-4 h-4 text-zinc-500" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $tl['title'] }}</p>
                            <p class="text-xs text-gray-500">
                                {{ Carbon::parse($tl['start_date'])->locale('id')->translatedFormat('d M Y') }}
                                –
                                {{ Carbon::parse($tl['end_date'])->locale('id')->translatedFormat('d M Y') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <flux:tooltip content="Edit">
                            <flux:button size="xs" variant="ghost" icon="pencil-square"
                                wire:click="openEditTimeline({{ $tl['id'] }})" />
                        </flux:tooltip>
                        <flux:tooltip content="Hapus">
                            <flux:button size="xs" variant="ghost" icon="trash"
                                wire:click="confirmDeleteTimeline({{ $tl['id'] }})" />
                        </flux:tooltip>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-12 text-center border rounded-xl bg-gray-50">
                <flux:icon name="flag" class="w-10 h-10 text-gray-300 mb-3" />
                <p class="text-sm font-medium text-gray-600">Belum ada timeline</p>
                <p class="text-xs text-gray-400 mt-1 mb-4">Mulai dengan menambahkan timeline pertama.</p>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateTimeline">
                    Tambah Timeline
                </flux:button>
            </div>
            @endif

            <div class="flex justify-end pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showManageModal', false)">
                    Tutup
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>

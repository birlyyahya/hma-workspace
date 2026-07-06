<?php

use App\Models\User;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use App\Services\SpdPdfComposer;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;
use App\Services\NotificationService;
use Livewire\Attributes\On;

new class extends Component {
    use WithFileUploads;

    public ?array $list = null;

    public int $page = 1;
    public int $perPage = 10;
    public string $search = '';
    public string $startDateFilter = '';
    public string $endDateFilter = '';

    public bool $loading = true;

    /** Form fields */
    public ?int $editingId = null;
    public ?int $userId = null;
    public string $userSearch = '';
    public ?int $number = null;
    public string $task = '';
    public string $department = '';
    public string $destination = '';
    public string $address = '';
    public string $masaTugas = '';
    public bool $isSubmitted = false;
    public bool $isApproved = false;
    public $attachment = null;
    public ?string $existingAttachmentUrl = null;

    public ?int $pendingDeleteId = null;

    public function mount(): void
    {
        $this->fetchList();
    }

    protected function rules(): array
    {
        return [
            'userId' => ['required', 'integer', 'exists:users,id'],
            'number' => ['required', 'integer', 'min:1'],
            'task' => ['required', 'string', 'min:3'],
            'department' => ['required', 'string'],
            'destination' => ['required', 'string'],
            'address' => ['required', 'string'],
            'masaTugas' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }

    protected function messages(): array
    {
        return [
            'userId.required' => 'Pilih pegawai untuk SPD ini.',
            'userId.exists' => 'Pegawai tidak valid.',
            'number.required' => 'Nomor surat wajib diisi.',
            'number.integer' => 'Nomor surat harus berupa angka.',
            'number.min' => 'Nomor surat minimal 1.',
            'task.required' => 'Tugas / pekerjaan wajib diisi.',
            'department.required' => 'Departemen / satuan kerja wajib diisi.',
            'destination.required' => 'Tujuan / lokasi wajib diisi.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'masaTugas.required' => 'Masa tugas / tanggal wajib diisi.',
            'attachment.max' => 'Lampiran maksimal 10 MB.',
        ];
    }

    /**
     * Rich-text editor menghasilkan HTML. Editor kosong tetap mengirim markup
     * seperti `<p><br></p>`, jadi normalkan menjadi string kosong bila tidak ada
     * teks maupun gambar — supaya rule `required` tetap berfungsi.
     */
    protected function normalizeHtml(string $html): string
    {
        if (trim(strip_tags($html)) === '' && ! Str::contains($html, '<img')) {
            return '';
        }

        return trim($html);
    }

    protected function romanMonth(int $month): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return $map[$month] ?? '';
    }

    public function formatSpdNumber(?int $number, ?string $createdAt = null): string
    {
        if (! $number) {
            return '-';
        }

        $date = $createdAt ? Carbon::parse($createdAt) : Carbon::now();

        return sprintf('HMA/IT RnD/SPD/%d/%s/%d', $number, $this->romanMonth((int) $date->month), $date->year);
    }

    protected function nextNumber(): int
    {
        $currentYear = Carbon::now()->year;

        $max = collect($this->list['data'] ?? [])
            ->filter(function ($row) use ($currentYear) {
                if (! isset($row['number']) || ! is_numeric($row['number'])) {
                    return false;
                }

                $createdAt = $row['created_at'] ?? null;
                $rowYear = $createdAt ? Carbon::parse($createdAt)->year : $currentYear;

                return $rowYear === $currentYear;
            })
            ->map(fn ($row) => (int) $row['number'])
            ->max();

        return ($max ?: 0) + 1;
    }

    #[Computed]
    public function rowUsers(): \Illuminate\Support\Collection
    {
        $ids = collect($this->list['data'] ?? [])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->get()->keyBy('id');
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    #[Computed]
    public function searchableUsers(): \Illuminate\Support\Collection
    {
        $term = Str::lower(trim($this->userSearch));

        $query = User::query()->orderBy('name');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"]);
            });
        }

        return $query->limit(20)->get();
    }

    public function selectUser(int $id): void
    {
        $this->userId = $id;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->userId = null;
        $this->userSearch = '';
    }

    public function fetchList(): void
    {
        $this->loading = true;

        $params = [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'search' => $this->search,
        ];

        if ($this->startDateFilter !== '') {
            $params['start_date'] = $this->startDateFilter;
        }

        if ($this->endDateFilter !== '') {
            $params['end_date'] = $this->endDateFilter;
        }

        if (! Auth::user()->hasPermission('spd.view.all')) {
            $params['user_id'] = Auth::user()->id;
        }

        $this->list = app(IzinCache::class)->spdList($params);
        $this->loading = false;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->fetchList();
    }

    public function updatedStartDateFilter(): void
    {
        $this->page = 1;
        $this->fetchList();
    }

    public function updatedEndDateFilter(): void
    {
        $this->page = 1;
        $this->fetchList();
    }

    public function resetDateFilter(): void
    {
        $this->startDateFilter = '';
        $this->endDateFilter = '';
        $this->page = 1;
        $this->fetchList();
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->fetchList();
    }

    #[On('open-create-spd')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->number = $this->nextNumber();
        Flux::modal('spd-form-modal')->show();
    }

    public function openEdit(int $id): void
    {
        $row = collect($this->list['data'] ?? [])->firstWhere('id', $id);

        if (! $row) {
            Toaster::error('Data SPD tidak ditemukan.');

            return;
        }

        $this->editingId = (int) $row['id'];
        $this->userId = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $this->userSearch = '';
        $this->number = isset($row['number']) && is_numeric($row['number']) ? (int) $row['number'] : null;
        $this->task = (string) ($row['task'] ?? '');
        $this->department = (string) ($row['department'] ?? '');
        $this->destination = (string) ($row['destination'] ?? '');
        $this->address = (string) ($row['address'] ?? '');
        $this->masaTugas = (string) ($row['start_date'] ?? '');
        $this->isSubmitted = (bool) ($row['is_submitted'] ?? false);
        $this->isApproved = (bool) ($row['is_approved'] ?? false);
        $this->attachment = null;
        $this->existingAttachmentUrl = $row['attachment_url'] ?? null;

        Flux::modal('spd-form-modal')->show();
    }

    public function updatedIsSubmitted($value): void
    {
        if (! $value) {
            $this->isApproved = false;
        }
    }

    public function saveSpd(): void
    {
        $this->task = $this->normalizeHtml($this->task);
        $this->department = $this->normalizeHtml($this->department);
        $this->destination = $this->normalizeHtml($this->destination);
        $this->address = $this->normalizeHtml($this->address);
        $this->masaTugas = $this->normalizeHtml($this->masaTugas);

        $this->validate();

        $payload = [
            'user_id' => $this->userId,
            'number' => $this->number,
            'task' => $this->task,
            'department' => $this->department,
            'destination' => $this->destination,
            'address' => $this->address,
            'start_date' => $this->masaTugas,
            'end_date' => '',
            'is_submitted' => $this->isSubmitted ? 1 : 0,
            'is_approved' => $this->isSubmitted && $this->isApproved ? 1 : 0,
        ];

        $file = null;

        if ($this->attachment) {
            $path = $this->attachment->getRealPath();
            $contents = $path ? file_get_contents($path) : false;

            if ($contents !== false && $contents !== '') {
                $file = [
                    'contents' => $contents,
                    'name' => $this->attachment->getClientOriginalName(),
                ];
            }
        }

        $isUpdate = $this->editingId !== null;

        $result = app(IzinWriter::class)->saveSpd($this->editingId, $payload, $file);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []) ?: ($result['body']['message'] ?? 'Gagal menyimpan SPD.'));

            return;
        }

        if (($payload['is_approved'] ?? 0) === 1) {
            $this->notifyApproved($result['body']['data'] ?? []);
        }

        Toaster::success($isUpdate ? 'SPD berhasil diperbarui.' : 'SPD berhasil dibuat.');
        Flux::modal('spd-form-modal')->close();
        $this->resetForm();
        $this->fetchList();
    }

    /**
     * Kirim notifikasi persetujuan (best-effort). Kegagalan notifikasi tidak
     * boleh menggagalkan proses simpan SPD yang sudah berhasil di sisi API.
     *
     * @param  array<string, mixed>  $data
     */
    protected function notifyApproved(array $data): void
    {
        try {
            $userId = $data['user_id'] ?? $this->userId;
            $user = $userId ? User::find($userId) : null;

            if (! $user) {
                return;
            }

            NotificationService::send($user, 'SPD Anda sudah disetujui, silakan cek email Anda.', $data);
        } catch (\Throwable $e) {
            Log::error('SPD notify exception', ['message' => $e->getMessage()]);
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->pendingDeleteId = $id;
        Flux::modal('spd-delete-modal')->show();
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteId = null;
        Flux::modal('spd-delete-modal')->close();
    }

    public function deleteSpd(): void
    {
        if (! $this->pendingDeleteId) {
            return;
        }

        $result = app(IzinWriter::class)->deleteSpd($this->pendingDeleteId);

        if (! $result['ok']) {
            Toaster::error($result['body']['message'] ?? 'Gagal menghapus SPD.');

            return;
        }

        Toaster::success('SPD berhasil dihapus.');
        $this->pendingDeleteId = null;
        Flux::modal('spd-delete-modal')->close();
        $this->fetchList();
    }

    public function downloadPdf(int $id)
    {
        $row = collect($this->list['data'] ?? [])->firstWhere('id', $id);

        if (! $row) {
            Toaster::error('Data SPD tidak ditemukan.');

            return;
        }

        $user = User::find($row['user_id'] ?? null);

        $pdfBytes = app(SpdPdfComposer::class)->render($row, $user);

        $filename = 'SPD-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . '.pdf';

        return response()->streamDownload(
            fn () => print ($pdfBytes),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'userId', 'userSearch', 'number', 'task', 'department', 'destination', 'address',
            'masaTugas', 'attachment', 'existingAttachmentUrl',
            'isSubmitted', 'isApproved',
        ]);
        $this->resetErrorBag();
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_izin_table');
    }


}; ?>

<div>
    @php
    $rows = $this->list['data'] ?? [];
    $total = $this->list['total'] ?? count($rows);
    $currentPage = $this->list['current_page'] ?? $page;
    $lastPage = $this->list['last_page'] ?? 1;
    @endphp
    {{-- ── Card container ── --}}
    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
        {{-- Header --}}
        <header class="flex flex-col gap-3 border-b border-zinc-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-zinc-100 text-zinc-700">
                    <flux:icon name="paper-airplane" class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-zinc-900">Surat Perjalanan Dinas</h2>
                    <p class="text-xs text-zinc-500">Kelola pengajuan perjalanan dinas (SPD).</p>
                </div>
            </div>

            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari tujuan, tugas, departemen..." class="w-full sm:w-56" />

                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="calendar" variant="ghost" size="sm" class="font-normal">
                        @if ($startDateFilter || $endDateFilter)
                            <span class="text-xs text-zinc-600">
                                {{ $startDateFilter ? Carbon::parse($startDateFilter)->format('d M') : '...' }}
                                –
                                {{ $endDateFilter ? Carbon::parse($endDateFilter)->format('d M') : '...' }}
                            </span>
                        @else
                            <span class="text-xs">Filter Tanggal</span>
                        @endif
                    </flux:button>
                    <flux:menu class="min-w-64">
                        <flux:menu.item disabled>
                            <flux:input wire:model.live="startDateFilter" type="date" label="Dari Tanggal" />
                        </flux:menu.item>
                        <flux:menu.item disabled>
                            <flux:input wire:model.live="endDateFilter" type="date" label="Sampai Tanggal" />
                        </flux:menu.item>
                        @if ($startDateFilter || $endDateFilter)
                            <flux:menu.item>
                                <flux:button size="sm" variant="ghost" class="w-full" wire:click="resetDateFilter" icon="x-mark">
                                    Reset filter
                                </flux:button>
                            </flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>

                @can('spd.create')
                <flux:button wire:click="openCreate" icon="plus-circle" variant="primary">
                    Buat SPD
                </flux:button>
                @endcan
            </div>
        </header>

        {{-- Body --}}
        <div class="relative">
            {{-- Overlay loading saat search / pagination / filter --}}
            <div
                wire:loading.flex
                wire:target="search,startDateFilter,endDateFilter,goToPage,resetDateFilter,fetchList"
                class="absolute inset-0 z-20 hidden items-center justify-center bg-white/60 backdrop-blur-sm"
            >
                <div class="flex flex-col items-center gap-2">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-zinc-900 border-t-transparent"></div>
                    <span class="text-sm text-zinc-600">Memuat data...</span>
                </div>
            </div>

            @if ($loading)
            <div class="space-y-2 p-5">
                @for ($i = 0; $i < 3; $i++) <div class="flex animate-pulse items-center gap-4 rounded-xl border border-zinc-100 bg-zinc-50/40 p-4">
                    <div class="h-10 w-10 rounded-lg bg-zinc-200"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-1/3 rounded bg-zinc-200"></div>
                        <div class="h-2 w-1/2 rounded bg-zinc-200"></div>
                    </div>
                    <div class="h-6 w-16 rounded-full bg-zinc-200"></div>
            </div>
            @endfor
        </div>
        @elseif (empty($rows))
        <div class="px-5 py-10 text-center">
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-zinc-100 text-zinc-400">
                <flux:icon name="paper-airplane" class="h-6 w-6" />
            </div>
            <p class="text-sm font-medium text-zinc-700">Belum ada SPD</p>
            <p class="mt-1 text-xs text-zinc-500">
                Klik <span class="font-semibold">Buat SPD</span> untuk membuat surat perjalanan dinas baru.
            </p>
        </div>
        @else
        {{-- Desktop table --}}
        <div class="hidden md:block overflow-visible">
            <table class="w-full text-left text-sm overflow-visible">
                <thead class="border-b border-zinc-100 bg-zinc-50/50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="pl-5 py-3 font-semibold">NO SPD</th>
                        <th class="px-5 py-3 font-semibold">Pegawai</th>
                        <th class="px-5 py-3 font-semibold">Tugas</th>
                        <th class="px-5 py-3 font-semibold">Tujuan</th>
                        <th class="px-5 py-3 font-semibold">Periode</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Lampiran</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y overflow-visible divide-zinc-100">
                    @foreach ($rows as $spd)
                    @php
                    $isSubmitted = (int) ($spd['is_submitted'] ?? 0) === 1;
                    $isApproved = (int) ($spd['is_approved'] ?? 0) === 1;
                    $rowUser = $this->rowUsers[$spd['user_id'] ?? null] ?? null;

                    if ($isApproved) {
                    $statusLabel = 'Disetujui';
                    $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                    $dotClass = 'bg-emerald-500';
                    } elseif ($isSubmitted) {
                    $statusLabel = 'Menunggu';
                    $statusClass = 'bg-blue-50 text-blue-800 ring-blue-200';
                    $dotClass = 'bg-blue-500';
                    } else {
                    $statusLabel = 'Pending';
                    $statusClass = 'bg-amber-100 text-amber-700 ring-amber-200';
                    $dotClass = 'bg-amber-400';
                    }
                    @endphp
                    <tr wire:key="spd-{{ $spd['id'] }}" class="transition hover:bg-zinc-50/60">
                        <td class="px-5 py-3.5 min-w-20 text-center">
                            <p class="font-semibold text-zinc-900 line-clamp-1">{{ $spd['number'] ? str_pad((string) ($spd['number'] ?? '0'), 2, '0', STR_PAD_LEFT) : '-'}}</p>
                        </td>
                        <td class=" py-3.5">
                            @if ($rowUser)
                            <div class="flex justify-center gap-2.5">
                                <flux:tooltip content="{{ $rowUser->name }}">
                                    <flux:avatar size="sm" :name="$rowUser->name" />
                                </flux:tooltip>
                            </div>
                            @else
                            <span class="text-xs italic text-zinc-400">Pegawai tidak ditemukan</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 max-w-[300px]">
                            <p class="font-semibold text-zinc-900 line-clamp-1">{{ strip_tags($spd['task'] ?? '') }}</p>
                            <p class="mt-0.5 text-xs text-zinc-500 line-clamp-1">{{ strip_tags($spd['department'] ?? '') }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-zinc-800 line-clamp-1">{{ strip_tags($spd['destination'] ?? '') }}</p>
                            <p class="mt-0.5 max-w-35 truncate text-xs text-zinc-500" title="{{ strip_tags($spd['address'] ?? '') }}">
                                {{ strip_tags($spd['address'] ?? '') }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-zinc-700">
                            <div class="flex items-start gap-1.5 text-xs">
                                <flux:icon name="calendar" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-zinc-400" />
                                <span class="line-clamp-2 max-w-40">{{ strip_tags($spd['start_date'] ?? '') ?: '—' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            @if (! empty($spd['attachment_url']))
                            <a href="{{ $spd['attachment_url'] }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline">
                                <flux:icon name="paper-clip" class="h-3.5 w-3.5" />
                                Lihat
                            </a>
                            @else
                            <span class="text-xs text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div x-data="{
                                                open: false,
                                                coords: { top: 0, left: 0 },
                                                toggle() {
                                                    this.open = !this.open;
                                                    if (this.open) this.$nextTick(() => this.updatePosition());
                                                },
                                                updatePosition() {
                                                    const btn = this.$refs.btn.getBoundingClientRect();
                                                    const menu = this.$refs.menu;
                                                    const menuH = menu.offsetHeight;
                                                    const menuW = menu.offsetWidth;
                                                    const spaceBelow = window.innerHeight - btn.bottom;
                                                    const top = spaceBelow < menuH + 12 ? btn.top - menuH - 4 : btn.bottom + 4;
                                                    const left = Math.max(8, btn.right - menuW);
                                                    this.coords = { top, left };
                                                },
                                            }" @keydown.escape.window="open = false" @resize.window="if (open) updatePosition()" @scroll.window.passive="if (open) updatePosition()" class="inline-block">
                                <button type="button" x-ref="btn" @click="toggle()" class="grid h-8 w-8 place-items-center rounded-full text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" aria-label="Aksi">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                        <circle cx="5" cy="12" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="19" cy="12" r="1.6" />
                                    </svg>
                                </button>
                                <div x-ref="menu" x-cloak x-show="open" @click.away="open = false" x-transition.origin.top.right :style="`top: ${coords.top}px; left: ${coords.left}px;`" class="fixed z-50 w-44 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-200/70">
                                    <a href="{{ route('izin.spd-preview', $spd['id']) }}" target="_blank" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                        <flux:icon name="eye" class="h-4 w-4" /> Preview
                                    </a>
                                    <button wire:click="downloadPdf({{ $spd['id'] }})" @click="open = false" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                        <flux:icon name="document-arrow-down" class="h-4 w-4" /> Download PDF
                                    </button>
                                    @can('spd.update')
                                    <div class="h-px bg-zinc-200/70"></div>
                                    <button wire:click="openEdit({{ $spd['id'] }})" @click="open = false" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                        <flux:icon name="pencil-square" class="h-4 w-4" /> Edit
                                    </button>
                                    @endcan
                                    @can('spd.delete')
                                    @if (!$isApproved)
                                    <button wire:click="confirmDelete({{ $spd['id'] }})" @click="open = false" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                        <flux:icon name="trash" class="h-4 w-4" /> Hapus
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-3 p-4 md:hidden">
            @foreach ($rows as $spd)
            @php
            $isSubmitted = (int) ($spd['is_submitted'] ?? 0) === 1;
            $isApproved = (int) ($spd['is_approved'] ?? 0) === 1;
            $rowUser = $this->rowUsers[$spd['user_id'] ?? null] ?? null;

            if ($isApproved) {
            $statusLabel = 'Disetujui';
            $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
            } elseif ($isSubmitted) {
            $statusLabel = 'Menunggu Persetujuan';
            $statusClass = 'bg-amber-50 text-amber-800 ring-amber-200';
            } else {
            $statusLabel = 'Pending';
            $statusClass = 'bg-zinc-100 text-zinc-700 ring-zinc-200';
            }
            @endphp
            <article wire:key="spd-mob-{{ $spd['id'] }}" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-zinc-900 line-clamp-2">{{ strip_tags($spd['task'] ?? '') }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500 line-clamp-1">{{ strip_tags($spd['department'] ?? '') }}</p>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                @if ($rowUser)
                <div class="mt-3 flex items-center gap-2 rounded-lg bg-zinc-50 px-2.5 py-2">
                    <flux:avatar size="xs" :name="$rowUser->name" />
                    <div class="min-w-0 text-xs">
                        <p class="truncate font-medium text-zinc-800">{{ $rowUser->name }}</p>
                        <p class="truncate text-zinc-500">{{ $rowUser->email }}</p>
                    </div>
                </div>
                @endif
                <p class="mt-2 text-sm text-zinc-700 line-clamp-2">{{ strip_tags($spd['destination'] ?? '') }}</p>
                <p class="mt-1 text-xs text-zinc-500 line-clamp-2">{{ strip_tags($spd['address'] ?? '') }}</p>
                <div class="mt-3 flex items-start gap-1.5 text-xs text-zinc-600">
                    <flux:icon name="calendar" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-zinc-400" />
                    <span class="line-clamp-2">{{ strip_tags($spd['start_date'] ?? '') ?: '—' }}</span>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-3">
                    @if (! empty($spd['attachment_url']))
                    <a href="{{ $spd['attachment_url'] }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600">
                        <flux:icon name="paper-clip" class="h-3.5 w-3.5" /> Lampiran
                    </a>
                    @else
                    <span></span>
                    @endif
                    <div class="flex items-center gap-1">
                        <a href="{{ route('izin.spd-preview', $spd['id']) }}" target="_blank">
                            <flux:button size="xs" variant="ghost" icon="eye">Preview</flux:button>
                        </a>
                        <flux:button size="xs" variant="ghost" icon="document-arrow-down" wire:click="downloadPdf({{ $spd['id'] }})">PDF</flux:button>
                        @if (! $isApproved)
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEdit({{ $spd['id'] }})">Edit</flux:button>
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmDelete({{ $spd['id'] }})">Hapus</flux:button>
                        @endif
                    </div>
                </div>
            </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($lastPage > 1)
        <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-5 py-3 text-xs text-zinc-600">
            <span>Halaman {{ $currentPage }} dari {{ $lastPage }} · {{ $total }} data</span>
            <div class="flex items-center gap-1">
                <button wire:click="goToPage({{ max(1, $currentPage - 1) }})" @disabled($currentPage <=1) class="rounded-lg px-3 py-1.5 text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50 disabled:opacity-40">
                    ← Prev
                </button>
                <button wire:click="goToPage({{ min($lastPage, $currentPage + 1) }})" @disabled($currentPage>= $lastPage)
                    class="rounded-lg px-3 py-1.5 text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50 disabled:opacity-40">
                    Next →
                </button>
            </div>
        </div>
        @endif
        @endif
</div>
</section>

{{-- ── Form modal (create / edit) ── --}}
<flux:modal name="spd-form-modal" class="min-w-2xl overflow-auto md:min-w-3xl lg:min-w-4xl">
    <form
        wire:submit="saveSpd"
        class="space-y-5"
        x-data="{ uploading: false }"
        x-on:livewire-upload-start.window="uploading = true"
        x-on:livewire-upload-finish.window="uploading = false"
        x-on:livewire-upload-error.window="uploading = false"
        x-on:livewire-upload-cancel.window="uploading = false"
    >
        <div class="flex items-start gap-3 border-b border-zinc-100 pb-4">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-zinc-100 text-zinc-700">
                <flux:icon name="paper-airplane" class="h-5 w-5" />
            </div>
            <div>
                <flux:heading size="lg" class="mb-0!">
                    {{ $editingId ? 'Edit SPD' : 'Buat SPD' }}
                </flux:heading>
                <p class="mt-0.5 text-sm text-zinc-500">Lengkapi detail perjalanan dinas berikut.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-4 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Pegawai (Untuk Siapa SPD Ini)</label>
                @if ($this->selectedUser)
                <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <flux:avatar size="sm" :name="$this->selectedUser->name" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-900">{{ $this->selectedUser->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $this->selectedUser->email }}</p>
                        </div>
                    </div>
                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="clearUser" type="button">Ganti</flux:button>
                </div>
                @else
                <div class="space-y-2">
                    <flux:input wire:model.live.debounce.300ms="userSearch" icon="magnifying-glass" placeholder="Cari nama, username, atau email pegawai..." />
                    <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1.5">
                        @forelse ($this->searchableUsers as $u)
                        <button wire:key="user-pick-{{ $u->id }}" type="button" wire:click="selectUser({{ $u->id }})" class="flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-zinc-50">
                            <flux:avatar size="xs" :name="$u->name" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900">{{ $u->name }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $u->email }}</p>
                            </div>
                            <flux:icon name="plus" class="h-4 w-4 text-zinc-400" />
                        </button>
                        @empty
                        <p class="px-3 py-4 text-center text-xs text-zinc-400">Tidak ada pegawai cocok.</p>
                        @endforelse
                    </div>
                </div>
                @endif
                @error('userId')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Nomor Surat</label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[140px_1fr]">
                    <flux:input wire:model.live="number" type="number" min="1" placeholder="Nomor" />
                    <div class="flex items-center rounded-xl border border-zinc-200 bg-zinc-50/60 px-3 py-2 text-sm text-zinc-700">
                        <span class="truncate font-mono">{{ $this->formatSpdNumber($number ? (int) $number : null) }}</span>
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-zinc-400">Hanya angka yang diinput. Bagian bulan & tahun mengikuti tanggal pembuatan.</p>
                @error('number')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tugas / Pekerjaan</label>
                <x-spd.rich-editor model="task" placeholder="Rincian tugas / pekerjaan (bisa berupa daftar)..." />
                <p class="mt-1 text-[11px] text-zinc-400">Gunakan tombol daftar untuk menuliskan beberapa tugas.</p>
                @error('task')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Departemen / Satuan Kerja</label>
                <x-spd.rich-editor model="department" placeholder="Departemen / satuan kerja..." />
                @error('department')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tujuan / Lokasi</label>
                <x-spd.rich-editor model="destination" placeholder="Tujuan / lokasi (bisa berupa daftar)..." />
                @error('destination')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Alamat Lengkap</label>
                <x-spd.rich-editor model="address" placeholder="Alamat lengkap..." />
                @error('address')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Masa Tugas / Tanggal</label>
                <x-spd.rich-editor model="masaTugas" placeholder="Contoh: 12 Januari 2026 s/d 14 Januari 2026 (bisa banyak periode)..." />
                <p class="mt-1 text-[11px] text-zinc-400">Tuliskan satu atau beberapa periode tanggal mulai &amp; selesai sebagai daftar.</p>
                @error('masaTugas')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Lampiran (opsional)</label>
                <div class="rounded-xl border-2 border-dashed border-zinc-200 bg-zinc-50/60 p-4">
                    <input wire:model="attachment" type="file" accept="image/*,application/pdf" class="block w-full text-sm text-zinc-700
                            file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5
                            file:text-xs file:font-semibold file:text-white hover:file:bg-zinc-800" />
                    @if ($attachment)
                    <p class="mt-2 inline-flex items-center gap-1.5 text-xs text-zinc-600">
                        <flux:icon name="paper-clip" class="h-3.5 w-3.5" />
                        {{ $attachment->getClientOriginalName() }}
                        <span class="text-zinc-400">· {{ formatFileSize($attachment->getSize()) }}</span>
                    </p>
                    @elseif ($existingAttachmentUrl)
                    <p class="mt-2 inline-flex items-center gap-1.5 text-xs text-zinc-600">
                        <flux:icon name="paper-clip" class="h-3.5 w-3.5" />
                        <a href="{{ $existingAttachmentUrl }}" target="_blank" class="text-blue-600 hover:underline">Lampiran saat ini</a>
                        <span class="text-zinc-400">· upload ulang untuk mengganti</span>
                    </p>
                    @endif
                    <p class="mt-1 text-[11px] text-zinc-400">Image (JPG/PNG) akan digabung otomatis ke PDF SPD. PDF dapat diunduh terpisah.</p>
                </div>
                @error('attachment')
                <flux:error message="{{ $message }}" /> @enderror
            </div>

            {{-- ── Tanda tangan / Status ── --}}
            <div x-data="{
                        submitted: @entangle('isSubmitted').live,
                        approved: @entangle('isApproved').live,
                    }" x-effect="if (! submitted) approved = false" class="lg:col-span-2">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanda Tangan &amp; Status</p>
                <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                    {{-- Ajukan (Diajukan oleh) --}}
                    <label class="flex cursor-pointer items-start gap-3">
                        <input x-model="submitted" type="checkbox" class="mt-0.5 h-4 w-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400" />
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-zinc-900">Ajukan (Diajukan oleh)</span>
                            <span class="block text-xs text-zinc-500">
                                Centang untuk menampilkan TTD <strong>Andre Lukmana Budhiarto</strong> (Manager IT RnD) dan mengirim untuk persetujuan.
                            </span>
                        </span>
                    </label>

                    {{-- Setujui (Menyetujui) — muncul kalau Ajukan dicentang --}}
                    <label x-show="submitted" x-cloak x-transition.opacity class="flex cursor-pointer items-start gap-3 border-t border-zinc-200 pt-3">
                        <input x-model="approved" type="checkbox" class="mt-0.5 h-4 w-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400" />
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-zinc-900">Setujui (Menyetujui)</span>
                            <span class="block text-xs text-zinc-500">
                                Centang untuk menampilkan TTD <strong>Ranap Irwan Rajagukguk</strong> (Direktur).
                            </span>
                        </span>
                    </label>

                    {{-- Status preview (Alpine reactive) --}}
                    <div class="flex items-center gap-2 border-t border-zinc-200 pt-3 text-xs text-zinc-500">
                        <span>Status saat ini:</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 font-semibold ring-1" :class="submitted && approved
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : (submitted
                                        ? 'bg-amber-50 text-amber-800 ring-amber-200'
                                        : 'bg-zinc-100 text-zinc-700 ring-zinc-200')" x-text="submitted && approved
                                    ? 'Disetujui'
                                    : (submitted ? 'Menunggu persetujuan Direktur Utama' : 'Pending')">
                            Pending
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-zinc-100 pt-4">
            @if ($editingId)
            <a href="{{ route('izin.spd-preview', $editingId) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50">
                <flux:icon name="eye" class="h-4 w-4" />
                Preview
            </a>
            @else
            <span></span>
            @endif

            <div class="flex items-center gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="saveSpd" x-bind:disabled="uploading">
                    <span x-show="uploading">Mengunggah lampiran...</span>
                    <span x-show="!uploading" wire:loading.remove wire:target="saveSpd">{{ $editingId ? 'Simpan Perubahan' : 'Buat SPD' }}</span>
                    <span x-show="!uploading" wire:loading wire:target="saveSpd">Menyimpan...</span>
                </flux:button>
            </div>
        </div>
    </form>
</flux:modal>

{{-- ── Delete confirmation modal ── --}}
<flux:modal name="spd-delete-modal" class="min-w-md" :dismissible="false">
    <div class="space-y-5">
        <div class="flex items-start gap-4">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                <flux:icon name="trash" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <flux:heading size="lg">Hapus SPD ini?</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600">
                    Data SPD akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="cancelDelete">Batal</flux:button>
            <flux:button variant="danger" wire:click="deleteSpd" wire:loading.attr="disabled" wire:target="deleteSpd">
                <span wire:loading.remove wire:target="deleteSpd">Hapus SPD</span>
                <span wire:loading wire:target="deleteSpd">Menghapus...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>

@assets
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<style>
    .spd-editor .ql-toolbar.ql-snow {
        border: none;
        border-bottom: 1px solid #e4e4e7;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
        background: #fafafa;
    }

    .spd-editor .ql-container.ql-snow {
        border: none;
        font-family: inherit;
        font-size: 0.875rem;
    }

    .spd-editor .ql-editor {
        min-height: 90px;
        max-height: 220px;
        overflow-y: auto;
    }

    .spd-editor .ql-editor.ql-blank::before {
        color: #a1a1aa;
        font-style: normal;
    }
</style>
@endassets

@script
<script>
    Alpine.data('spdRichEditor', (model) => ({
        value: model,
        quill: null,
        syncing: false,

        init() {
            this.whenQuillReady(() => this.mountEditor());
        },

        whenQuillReady(callback, tries = 0) {
            if (window.Quill) {
                callback();
                return;
            }

            if (tries > 200) {
                console.error('Quill editor gagal dimuat.');
                return;
            }

            setTimeout(() => this.whenQuillReady(callback, tries + 1), 50);
        },

        mountEditor() {
            this.quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                placeholder: this.$refs.editor.dataset.placeholder || '',
                modules: {
                    toolbar: [[{ list: 'ordered' }, { list: 'bullet' }]],
                },
            });

            if (this.value) {
                this.quill.clipboard.dangerouslyPasteHTML(this.value);
            }

            this.quill.on('text-change', () => {
                this.syncing = true;
                const html = this.quill.root.innerHTML;
                this.value = html === '<p><br></p>' ? '' : html;
                this.syncing = false;
            });

            this.$watch('value', (incoming) => {
                if (this.syncing) {
                    return;
                }

                const current = this.quill.root.innerHTML;
                const next = incoming || '';

                if (next !== current && !(next === '' && current === '<p><br></p>')) {
                    this.quill.clipboard.dangerouslyPasteHTML(next);
                }
            });
        },
    }));
</script>
@endscript

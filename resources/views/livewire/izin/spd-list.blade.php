<?php

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public ?array $list = null;

    public int $page = 1;
    public int $perPage = 10;
    public string $search = '';

    public bool $loading = true;

    /** Form fields */
    public ?int $editingId = null;
    public string $task = '';
    public string $department = '';
    public string $destination = '';
    public string $address = '';
    public string $startDate = '';
    public string $endDate = '';
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
            'task' => ['required', 'string', 'min:3'],
            'department' => ['required', 'string'],
            'destination' => ['required', 'string'],
            'address' => ['required', 'string'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }

    protected function messages(): array
    {
        return [
            'endDate.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'attachment.max' => 'Lampiran maksimal 10 MB.',
        ];
    }

    public function fetchList(): void
    {
        $this->loading = true;

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');
            $response = Http::timeout(60)
                ->retry(2, 200)
                ->get($apiIzin . '/global/dar/activity/list-spd', [
                    'page' => $this->page,
                    'per_page' => $this->perPage,
                    'search' => $this->search,
                ])->json();

            $this->list = $response ?? ['data' => []];
        } catch (\Throwable $e) {
            Toaster::error('Server SPD error, silakan coba lagi.');
            Log::error('SPD list API failed', ['message' => $e->getMessage()]);
            $this->list = ['data' => []];
        } finally {
            $this->loading = false;
        }
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->fetchList();
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->fetchList();
    }

    public function openCreate(): void
    {
        $this->resetForm();
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
        $this->task = (string) ($row['task'] ?? '');
        $this->department = (string) ($row['department'] ?? '');
        $this->destination = (string) ($row['destination'] ?? '');
        $this->address = (string) ($row['address'] ?? '');
        $this->startDate = $row['start_date'] ? Carbon::parse($row['start_date'])->format('Y-m-d') : '';
        $this->endDate = $row['end_date'] ? Carbon::parse($row['end_date'])->format('Y-m-d') : '';
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
        $this->validate();

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');

            $request = Http::asMultipart()->timeout(60);

            if ($this->attachment) {
                $request = $request->attach(
                    'attachment',
                    file_get_contents($this->attachment->getRealPath()),
                    $this->attachment->getClientOriginalName(),
                );
            }

            $payload = [
                'user_id' => Auth::id(),
                'task' => $this->task,
                'department' => $this->department,
                'destination' => $this->destination,
                'address' => $this->address,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'is_submitted' => $this->isSubmitted ? 1 : 0,
                'is_approved' => $this->isSubmitted && $this->isApproved ? 1 : 0,
            ];

            if ($this->editingId) {
                $payload['_method'] = 'PUT';
                $response = $request->post($apiIzin . '/global/dar/activity/update-spd/' . $this->editingId, $payload);
            } else {
                $response = $request->post($apiIzin . '/global/dar/activity/create-spd', $payload);
            }

            $body = $response->json();

            if (! ($body['success'] ?? false)) {
                Toaster::error(getErrorMessages($body['errors'] ?? []) ?: ($body['message'] ?? 'Gagal menyimpan SPD.'));
                Log::error('SPD save failed', ['body' => $body]);

                return;
            }

            Toaster::success($this->editingId ? 'SPD berhasil diperbarui.' : 'SPD berhasil dibuat.');
            Flux::modal('spd-form-modal')->close();
            $this->resetForm();
            $this->fetchList();
        } catch (\Throwable $e) {
            Toaster::error('Terjadi kesalahan saat menyimpan SPD.');
            Log::error('SPD save exception', ['message' => $e->getMessage()]);
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

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');
            $response = Http::delete($apiIzin . '/global/dar/activity/delete-spd/' . $this->pendingDeleteId)->json();

            if (! ($response['success'] ?? false)) {
                Toaster::error($response['message'] ?? 'Gagal menghapus SPD.');

                return;
            }

            Toaster::success('SPD berhasil dihapus.');
            $this->pendingDeleteId = null;
            Flux::modal('spd-delete-modal')->close();
            $this->fetchList();
        } catch (\Throwable $e) {
            Toaster::error('Server error saat menghapus SPD.');
            Log::error('SPD delete exception', ['message' => $e->getMessage()]);
        }
    }

    public function downloadPdf(int $id)
    {
        $row = collect($this->list['data'] ?? [])->firstWhere('id', $id);

        if (! $row) {
            Toaster::error('Data SPD tidak ditemukan.');

            return;
        }

        $user = User::find($row['user_id'] ?? null);

        $attachmentImage = $this->fetchAttachmentImage($row['attachment_url'] ?? null);

        $pdf = Pdf::loadView('pdf.spd-pdf', [
            'spd' => $row,
            'user' => $user,
            'attachmentImage' => $attachmentImage,
        ])->setPaper('A4', 'portrait');

        $filename = 'SPD-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . '.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
        );
    }

    /**
     * Fetch attachment and convert to base64 data URI if it's an image,
     * so DomPDF can render it as the second page.
     *
     * @return array{data:string,mime:string}|null
     */
    protected function fetchAttachmentImage(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $body) ?: 'image/png';

            return [
                'data' => 'data:' . $mime . ';base64,' . base64_encode($body),
                'mime' => $mime,
            ];
        } catch (\Throwable $e) {
            Log::warning('SPD attachment fetch failed', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'task', 'department', 'destination', 'address',
            'startDate', 'endDate', 'attachment', 'existingAttachmentUrl',
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
                <flux:input
                    wire:model.live.debounce.400ms="search"
                    icon="magnifying-glass"
                    placeholder="Cari tujuan, tugas, departemen..."
                    class="w-full sm:w-64"
                />
                <flux:button wire:click="openCreate" icon="plus-circle" variant="primary">
                    Buat SPD
                </flux:button>
            </div>
        </header>

        {{-- Body --}}
        <div class="relative">
            @if ($loading)
                <div class="space-y-2 p-5">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="flex animate-pulse items-center gap-4 rounded-xl border border-zinc-100 bg-zinc-50/40 p-4">
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
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-100 bg-zinc-50/50 text-xs uppercase tracking-wide text-zinc-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Tugas</th>
                                <th class="px-5 py-3 font-semibold">Tujuan</th>
                                <th class="px-5 py-3 font-semibold">Periode</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">Lampiran</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($rows as $spd)
                                @php
                                    $isSubmitted = (int) ($spd['is_submitted'] ?? 0) === 1;
                                    $isApproved = (int) ($spd['is_approved'] ?? 0) === 1;

                                    if ($isApproved) {
                                        $statusLabel = 'Disetujui';
                                        $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                                        $dotClass = 'bg-emerald-500';
                                    } elseif ($isSubmitted) {
                                        $statusLabel = 'Menunggu persetujuan Direktur';
                                        $statusClass = 'bg-amber-50 text-amber-800 ring-amber-200';
                                        $dotClass = 'bg-amber-500';
                                    } else {
                                        $statusLabel = 'Pending';
                                        $statusClass = 'bg-zinc-100 text-zinc-700 ring-zinc-200';
                                        $dotClass = 'bg-zinc-400';
                                    }
                                @endphp
                                <tr wire:key="spd-{{ $spd['id'] }}" class="transition hover:bg-zinc-50/60">
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-zinc-900">{{ $spd['task'] }}</p>
                                        <p class="mt-0.5 text-xs text-zinc-500">{{ $spd['department'] }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="text-zinc-800">{{ $spd['destination'] }}</p>
                                        <p class="mt-0.5 max-w-xs truncate text-xs text-zinc-500" title="{{ $spd['address'] }}">
                                            {{ $spd['address'] }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3.5 text-zinc-700">
                                        <div class="flex items-center gap-1.5 text-xs">
                                            <flux:icon name="calendar" class="h-3.5 w-3.5 text-zinc-400" />
                                            <span>{{ Carbon::parse($spd['start_date'])->format('d M Y') }}</span>
                                            <span class="text-zinc-300">→</span>
                                            <span>{{ Carbon::parse($spd['end_date'])->format('d M Y') }}</span>
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
                                            <a href="{{ $spd['attachment_url'] }}" target="_blank"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline">
                                                <flux:icon name="paper-clip" class="h-3.5 w-3.5" />
                                                Lihat
                                            </a>
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div x-data="{ open: false }" class="relative inline-block" @keydown.escape.window="open = false">
                                            <button type="button" @click="open = !open"
                                                class="grid h-8 w-8 place-items-center rounded-full text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700"
                                                aria-label="Aksi">
                                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                                    <circle cx="5" cy="12" r="1.6" />
                                                    <circle cx="12" cy="12" r="1.6" />
                                                    <circle cx="19" cy="12" r="1.6" />
                                                </svg>
                                            </button>
                                            <div x-cloak x-show="open" @click.away="open = false"
                                                x-transition.origin.top.right
                                                class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-200/70">
                                                <a href="{{ route('izin.spd-preview', $spd['id']) }}" target="_blank"
                                                    @click="open = false"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                                    <flux:icon name="eye" class="h-4 w-4" /> Preview
                                                </a>
                                                <button wire:click="downloadPdf({{ $spd['id'] }})" @click="open = false" type="button"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                                    <flux:icon name="document-arrow-down" class="h-4 w-4" /> Download PDF
                                                </button>
                                                @if (! $isApproved)
                                                    <div class="h-px bg-zinc-200/70"></div>
                                                    <button wire:click="openEdit({{ $spd['id'] }})" @click="open = false" type="button"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                                        <flux:icon name="pencil-square" class="h-4 w-4" /> Edit
                                                    </button>
                                                    <button wire:click="confirmDelete({{ $spd['id'] }})" @click="open = false" type="button"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                                        <flux:icon name="trash" class="h-4 w-4" /> Hapus
                                                    </button>
                                                @endif
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
                        <article wire:key="spd-mob-{{ $spd['id'] }}"
                            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-zinc-900">{{ $spd['task'] }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ $spd['department'] }}</p>
                                </div>
                                <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-700">{{ $spd['destination'] }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $spd['address'] }}</p>
                            <div class="mt-3 flex items-center gap-1.5 text-xs text-zinc-600">
                                <flux:icon name="calendar" class="h-3.5 w-3.5 text-zinc-400" />
                                {{ Carbon::parse($spd['start_date'])->format('d M Y') }} →
                                {{ Carbon::parse($spd['end_date'])->format('d M Y') }}
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-3">
                                @if (! empty($spd['attachment_url']))
                                    <a href="{{ $spd['attachment_url'] }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-blue-600">
                                        <flux:icon name="paper-clip" class="h-3.5 w-3.5" /> Lampiran
                                    </a>
                                @else
                                    <span></span>
                                @endif
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('izin.spd-preview', $spd['id']) }}" target="_blank">
                                        <flux:button size="xs" variant="ghost" icon="eye">Preview</flux:button>
                                    </a>
                                    <flux:button size="xs" variant="ghost" icon="document-arrow-down"
                                        wire:click="downloadPdf({{ $spd['id'] }})">PDF</flux:button>
                                    @if (! $isApproved)
                                        <flux:button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEdit({{ $spd['id'] }})">Edit</flux:button>
                                        <flux:button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDelete({{ $spd['id'] }})">Hapus</flux:button>
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
                            <button wire:click="goToPage({{ max(1, $currentPage - 1) }})" @disabled($currentPage <= 1)
                                class="rounded-lg px-3 py-1.5 text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50 disabled:opacity-40">
                                ← Prev
                            </button>
                            <button wire:click="goToPage({{ min($lastPage, $currentPage + 1) }})" @disabled($currentPage >= $lastPage)
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
        <form wire:submit="saveSpd" class="space-y-5">
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
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tugas / Pekerjaan</label>
                    <flux:input wire:model="task" placeholder="Contoh: Survei instalasi" />
                    @error('task') <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Departemen / Satuan Kerja</label>
                    <flux:input wire:model="department" placeholder="Contoh: Wilayah Jawa Barat" />
                    @error('department') <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tujuan / Lokasi</label>
                    <flux:input wire:model="destination" placeholder="Contoh: Karoseri Ottoone" />
                    @error('destination') <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Alamat Lengkap</label>
                    <flux:textarea wire:model="address" rows="2" placeholder="Jl. ..." />
                    @error('address') <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal Mulai</label>
                    <flux:input wire:model="startDate" type="date" />
                    @error('startDate') <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal Selesai</label>
                    <flux:input wire:model="endDate" type="date" />
                    @error('endDate') <flux:error message="{{ $message }}" /> @enderror
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
                    @error('attachment') <flux:error message="{{ $message }}" /> @enderror
                </div>

                {{-- ── Tanda tangan / Status ── --}}
                <div
                    x-data="{
                        submitted: @entangle('isSubmitted').live,
                        approved: @entangle('isApproved').live,
                    }"
                    x-effect="if (! submitted) approved = false"
                    class="lg:col-span-2"
                >
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanda Tangan &amp; Status</p>
                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                        {{-- Ajukan (Diajukan oleh) --}}
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                x-model="submitted"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400"
                            />
                            <span class="flex-1">
                                <span class="block text-sm font-semibold text-zinc-900">Ajukan (Diajukan oleh)</span>
                                <span class="block text-xs text-zinc-500">
                                    Centang untuk menampilkan TTD <strong>Andre Lukmana Budhiarto</strong> (Manager IT RnD) dan mengirim untuk persetujuan.
                                </span>
                            </span>
                        </label>

                        {{-- Setujui (Menyetujui) — muncul kalau Ajukan dicentang --}}
                        <label
                            x-show="submitted"
                            x-cloak
                            x-transition.opacity
                            class="flex cursor-pointer items-start gap-3 border-t border-zinc-200 pt-3"
                        >
                            <input
                                x-model="approved"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400"
                            />
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
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 font-semibold ring-1"
                                :class="submitted && approved
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : (submitted
                                        ? 'bg-amber-50 text-amber-800 ring-amber-200'
                                        : 'bg-zinc-100 text-zinc-700 ring-zinc-200')"
                                x-text="submitted && approved
                                    ? 'Disetujui'
                                    : (submitted ? 'Menunggu persetujuan Direktur Utama' : 'Pending')"
                            >
                                Pending
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-zinc-100 pt-4">
                @if ($editingId)
                    <a href="{{ route('izin.spd-preview', $editingId) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50">
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
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="check"
                        wire:loading.attr="disabled"
                        wire:target="saveSpd"
                    >
                        <span wire:loading.remove wire:target="saveSpd">{{ $editingId ? 'Simpan Perubahan' : 'Buat SPD' }}</span>
                        <span wire:loading wire:target="saveSpd">Menyimpan...</span>
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
                <flux:button variant="danger" wire:click="deleteSpd"
                    wire:loading.attr="disabled" wire:target="deleteSpd">
                    <span wire:loading.remove wire:target="deleteSpd">Hapus SPD</span>
                    <span wire:loading wire:target="deleteSpd">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

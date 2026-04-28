<?php

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;


new #[Lazy(isolate: false)]  class extends Component {
    use WithFileUploads;

    public array $companies = [];
    public array $pagination = [];
    public string $search = '';
    public int $limit = 10;
    public int $currentPage = 1;

    public bool $showForm = false;
    public bool $showDelete = false;
    public bool $isEdit = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $address = '';
    public string $director_name = '';
    public string $established_date = '';
    public $director_signature = null;
    public ?string $existing_signature = null;

    public ?int $deletingId = null;
    public ?string $deletingName = null;

    public bool $loading = false;
    public ?string $errorMessage = null;

    protected function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'address'           => ['required', 'string', 'max:1000'],
            'director_name'     => ['required', 'string', 'max:255'],
            'established_date'  => ['required', 'date'],
            'director_signature' => [$this->isEdit ? 'nullable' : 'required', 'image', 'max:2048'],
        ];
    }

    public function mount(): void
    {
        $this->fetchCompanies();
    }

    public function fetchCompanies(): void
    {
        $this->loading = true;
        $this->errorMessage = null;

        try {
            $response = Http::get(config('services.api_project') . 'companies/search', [
                'page'   => $this->currentPage,
                'limit'  => $this->limit,
                'name' => $this->search,
            ])->json();

            $this->companies  = $response['data'] ?? [];
            $this->pagination = $response['pagination'] ?? [];
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal memuat data perusahaan. Silakan coba lagi.';
            $this->companies   = [];
            $this->pagination  = [];
        } finally {
            $this->loading = false;
        }
    }

    public function applyFilters(): void
    {
        $this->currentPage = 1;
        $this->fetchCompanies();
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = $page;
        $this->fetchCompanies();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->isEdit    = false;
        $this->showForm  = true;
    }

    public function openEdit(int $id): void
    {
        $company = collect($this->companies)->firstWhere('id', $id);
        if (!$company) {
            return;
        }

        $this->resetForm();
        $this->isEdit             = true;
        $this->editingId          = $id;
        $this->name               = $company['name'] ?? '';
        $this->address            = $company['address'] ?? '';
        $this->director_name      = $company['director_name'] ?? '';
        $this->established_date   = $company['established_date'] ?? '';
        $this->existing_signature = $company['director_signature'] ?? null;
        $this->showForm           = true;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $request = Http::asMultipart();

            if ($this->director_signature) {
                $request = $request->attach(
                    'director_signature',
                    file_get_contents($this->director_signature->getRealPath()),
                    $this->director_signature->getClientOriginalName()
                );
            }

            $payload = [
                'name'             => $this->name,
                'address'          => $this->address,
                'director_name'    => $this->director_name,
                'established_date' => $this->established_date,
            ];

            $url = config('services.api_project') . 'companies'
                . ($this->isEdit ? '/' . $this->editingId : '');

            $response = $this->isEdit
                ? $request->post($url . '?_method=PUT', $payload)
                : $request->post($url, $payload);

            if (!$response->successful()) {
                $this->errorMessage = $response->json('message') ?? 'Gagal menyimpan data.';
                Toaster::error(getErrorMessages($response->json()));
                return;
            }

            $this->showForm = false;
            $this->resetForm();
            $this->fetchCompanies();
            Toaster::success($this->isEdit ? 'Perusahaan diperbarui' : 'Perusahaan ditambahkan');
        } catch (\Throwable $e) {
            $this->errorMessage = 'Terjadi kesalahan saat menyimpan.';
            Toaster::error($this->errorMessage);
             \Log::error('delete API failed', [
                'success' => $response['success'] ?? null,
                'status' => $response['status'] ?? null,
                'body' => method_exists($response, 'body') ? $response->body() : null,
            ]);
        }
    }

    public function confirmDelete(int $id): void
    {
        $company = collect($this->companies)->firstWhere('id', $id);
        $this->deletingId   = $id;
        $this->deletingName = $company['name'] ?? null;
        $this->showDelete   = true;
    }

    public function delete(): void
    {
        if (!$this->deletingId) {
            return;
        }

        try {
            $response = Http::delete(config('services.api_project') . 'companies/' . $this->deletingId);

            if (!$response->successful()) {
                $this->errorMessage = $response->json('message') ?? 'Gagal menghapus data.';
                Toaster::error(getErrorMessages($response->json()));
                return;
            }

            $this->showDelete   = false;
            $this->deletingId   = null;
            $this->deletingName = null;
            $this->fetchCompanies();
            Toaster::success('Perusahaan dihapus');
        } catch (\Throwable $e) {
            $this->errorMessage = 'Terjadi kesalahan saat menghapus.';
            Toaster::error($this->errorMessage);
            \Log::error('delete API failed', [
                'success' => $response['success'] ?? null,
                'status' => $response['status'] ?? null,
                'body' => method_exists($response, 'body') ? $response->body() : null,
            ]);
        }
    }

    public function resetForm(): void
    {
        $this->reset([
            'isEdit', 'editingId', 'name', 'address', 'director_name',
            'established_date', 'director_signature', 'existing_signature', 'errorMessage',
        ]);
        $this->resetValidation();
    }
    public function placeholder(): string
    {
        return view('components.placeholder.ph_perusahaan');
    }
}; ?>

<div class="space-y-6 max-h-screen overflow-auto px-2 py-4">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading class="text-2xl font-bold">Perusahaan</flux:heading>
            <flux:description>
                @if(!empty($pagination['total']))
                Menampilkan {{ count($companies) }} dari {{ $pagination['total'] }} perusahaan
                @else
                Kelola data perusahaan
                @endif
            </flux:description>
        </div>
        <flux:button icon="plus-circle" wire:click="openCreate" variant="primary" class="w-full sm:w-auto shrink-0">
            Tambah Perusahaan
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="relative">
        <flux:input icon="magnifying-glass" wire:model="search" wire:keydown.enter="applyFilters" wire:loading.attr="disabled" placeholder="Cari nama perusahaan atau direktur..." class="w-full" />
        <div wire:loading wire:target="applyFilters,goToPage" class="absolute right-3 top-1/2 -translate-y-1/2">
            <flux:icon name="arrow-path" class="w-4 h-4 text-zinc-400 animate-spin" />
        </div>
    </div>

    {{-- Error State --}}
    @if($errorMessage && !$showForm && !$showDelete)
    <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 p-4 flex items-start gap-3">
        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
        <div class="flex-1 text-sm text-red-700 dark:text-red-400">{{ $errorMessage }}</div>
        <flux:button size="sm" variant="ghost" wire:click="fetchCompanies">Coba lagi</flux:button>
    </div>
    @endif

    {{-- Desktop Table --}}
    <div class="hidden md:block bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden" wire:loading.remove wire:target="applyFilters,goToPage,fetchCompanies">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Perusahaan</th>
                        <th class="px-5 py-3 font-semibold">Direktur</th>
                        <th class="px-5 py-3 font-semibold">Tanda Tangan</th>
                        <th class="px-5 py-3 font-semibold">Berdiri</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($companies as $item)
                    <tr wire:key="company-{{ $item['id'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                        <td class="px-5 py-4 align-top">
                            <div class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $item['name'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2 max-w-md">
                                {{ $item['address'] }}
                            </div>
                        </td>
                        <td class="px-5 py-4 align-top text-zinc-700 dark:text-zinc-300">
                            {{ $item['director_name'] ?? '-' }}
                        </td>
                        <td class="px-5 py-4 align-top">
                            @if(!empty($item['director_signature']))
                            <a href="{{ config('services.url_project') . $item['director_signature'] }}" target="_blank" class="inline-block w-20 h-12 rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 overflow-hidden hover:ring-2 hover:ring-blue-400 transition">
                                <img src="{{ config('services.url_project') . $item['director_signature'] }}" alt="ttd" class="w-full h-full object-contain" />
                            </a>
                            @else
                            <span class="text-xs text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-top text-zinc-600 dark:text-zinc-400">
                            {{ $item['established_date'] ? \Carbon\Carbon::parse($item['established_date'])->translatedFormat('d M Y') : '-' }}
                        </td>
                        <td class="px-5 py-4 align-top">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEdit({{ $item['id'] }})">
                                    Edit
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $item['id'] }})" class="text-red-600 hover:text-red-700">
                                    Hapus
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16">
                            <div class="flex flex-col items-center justify-center text-center">
                                <div class="w-16 h-16 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-4">
                                    <flux:icon name="building-office-2" class="w-8 h-8 text-zinc-400" />
                                </div>
                                <p class="text-zinc-600 dark:text-zinc-400 font-medium">Belum ada perusahaan</p>
                                <p class="text-zinc-400 dark:text-zinc-500 text-sm mt-1">
                                    @if($search)
                                    Tidak ada hasil untuk "{{ $search }}"
                                    @else
                                    Tambahkan perusahaan pertama Anda
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Cards --}}
    <div class="md:hidden space-y-3" wire:loading.remove wire:target="applyFilters,goToPage,fetchCompanies">
        @forelse($companies as $item)
        <div wire:key="m-company-{{ $item['id'] }}" class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-sm p-4 space-y-3">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    @if(!empty($item['director_signature']))
                    <img src="{{ $item['director_signature'] }}" class="w-full h-full object-contain rounded-lg" />
                    @else
                    <flux:icon name="building-office-2" class="w-5 h-5 text-zinc-400" />
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-zinc-800 dark:text-zinc-100 line-clamp-2">{{ $item['name'] }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $item['director_name'] ?? '-' }}</div>
                </div>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $item['address'] }}</p>
            <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <span class="text-xs text-zinc-400">
                    {{ $item['established_date'] ? \Carbon\Carbon::parse($item['established_date'])->translatedFormat('d M Y') : '-' }}
                </span>
                <div class="flex gap-1">
                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEdit({{ $item['id'] }})" />
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmDelete({{ $item['id'] }})" class="text-red-600" />
                </div>
            </div>
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-4">
                <flux:icon name="building-office-2" class="w-8 h-8 text-zinc-400" />
            </div>
            <p class="text-zinc-600 dark:text-zinc-400 font-medium">Belum ada perusahaan</p>
        </div>
        @endforelse
    </div>

    {{-- Loading Skeleton --}}
    <div wire:loading wire:target="applyFilters,goToPage,fetchCompanies" class="bg-white w-full dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 p-5 space-y-3">
        @foreach(range(1, 2) as $_)
        <flux:skeleton.group animate="shimmer" class="flex items-center gap-4">
            <flux:skeleton class="h-10 w-10 rounded-lg" />
            <div class="flex-1 space-y-2">
                <flux:skeleton class="h-3 w-1/3 rounded" />
                <flux:skeleton class="h-3 w-2/3 rounded" />
            </div>
            <flux:skeleton class="h-8 w-20 rounded-md" />
        </flux:skeleton.group>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if(!empty($pagination) && ($pagination['last_page'] ?? 1) > 1)
    @php
    $lastPage = $pagination['last_page'];
    $activePage = $currentPage;
    $pages = collect(range(1, $lastPage))->filter(
    fn($p) => $p === 1 || $p === $lastPage || abs($p - $activePage) <= 2 )->values();
        @endphp

        <div wire:loading.remove wire:target="applyFilters,goToPage,fetchCompanies" class="flex items-center justify-center gap-1.5 pt-2 flex-wrap">
            <flux:button wire:click="goToPage({{ max(1, $currentPage - 1) }})" :disabled="$currentPage <= 1" variant="outline" icon="chevron-left" size="sm" />
            @foreach($pages as $i => $page)
            @if($i > 0 && $page - $pages[$i - 1] > 1)
            <span class="text-zinc-400 px-1 text-sm">…</span>
            @endif
            <flux:button wire:click="goToPage({{ $page }})" variant="{{ $page === $currentPage ? 'primary' : 'outline' }}" size="sm" class="w-9">{{ $page }}</flux:button>
            @endforeach
            <flux:button wire:click="goToPage({{ min($lastPage, $currentPage + 1) }})" :disabled="$currentPage >= $lastPage" variant="outline" icon="chevron-right" size="sm" />
        </div>

        <p wire:loading.remove wire:target="applyFilters,goToPage,fetchCompanies" class="text-center text-xs text-zinc-400 dark:text-zinc-500 pb-2">
            Halaman {{ $currentPage }} dari {{ $lastPage }} &middot; Total {{ $pagination['total'] }} perusahaan
        </p>
        @endif

        {{-- Create / Edit Modal --}}
        <flux:modal wire:model="showForm" class="md:w-[640px]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $isEdit ? 'Edit Perusahaan' : 'Tambah Perusahaan' }}</flux:heading>
                    <flux:description>
                        {{ $isEdit ? 'Perbarui informasi perusahaan.' : 'Lengkapi data perusahaan baru.' }}
                    </flux:description>
                </div>

                @if($errorMessage)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 px-3 py-2 text-sm text-red-700 dark:text-red-400">
                    {{ $errorMessage }}
                </div>
                @endif

                <form wire:submit="save" class="space-y-5">
                    <flux:input wire:model="name" label="Nama Perusahaan" placeholder="CV. Contoh Jaya" required />

                    <flux:textarea wire:model="address" label="Alamat" rows="3" placeholder="Alamat lengkap perusahaan" required />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:input wire:model="director_name" label="Nama Direktur" placeholder="Nama lengkap" required />
                        <flux:input wire:model="established_date" type="date" label="Tanggal Berdiri" required />
                    </div>

                    {{-- Signature Upload --}}
                    <div>
                        <flux:label>Tanda Tangan Direktur</flux:label>
                        <div class="mt-1.5 flex items-start gap-4">
                            <div class="w-32 h-20 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center overflow-hidden shrink-0">
                                @if($director_signature)
                                <img src="{{ config('services.url_project') . $director_signature->temporaryUrl() }}" class="w-full h-full object-contain" />
                                @elseif($existing_signature)
                                <img src="{{ config('services.url_project') . $existing_signature }}" class="w-full h-full object-contain" />
                                @else
                                <flux:icon name="photo" class="w-6 h-6 text-zinc-400" />
                                @endif
                            </div>
                            <div class="flex-1 space-y-2">
                                <input type="file" wire:model="director_signature" accept="image/*" class="block w-full text-xs text-zinc-600 dark:text-zinc-400
                                          file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0
                                          file:text-xs file:font-semibold
                                          file:bg-zinc-100 dark:file:bg-zinc-800 file:text-zinc-700 dark:file:text-zinc-300
                                          hover:file:bg-zinc-200 dark:hover:file:bg-zinc-700 cursor-pointer" />
                                <p class="text-xs text-zinc-400">PNG/JPG, maks 2 MB. Latar transparan disarankan.</p>
                                <div wire:loading wire:target="director_signature" class="text-xs text-zinc-500">Mengunggah…</div>
                            </div>
                        </div>
                        @error('director_signature')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Batal</flux:button>
                        <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan' }}</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Delete Confirmation Modal --}}
        <flux:modal wire:model="showDelete" class="md:w-[440px]">
            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <flux:heading size="lg">Hapus Perusahaan?</flux:heading>
                        <flux:description>
                            Data <strong>{{ $deletingName ?? 'perusahaan ini' }}</strong> akan dihapus permanen dan tidak dapat dikembalikan.
                        </flux:description>
                    </div>
                </div>

                @if($errorMessage)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 px-3 py-2 text-sm text-red-700 dark:text-red-400">
                    {{ $errorMessage }}
                </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showDelete', false)">Batal</flux:button>
                    <flux:button variant="danger" icon="trash" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                        <span wire:loading.remove wire:target="delete">Ya, Hapus</span>
                        <span wire:loading wire:target="delete">Menghapus…</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
</div>

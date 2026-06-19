<?php

use App\Livewire\Forms\FilesForm;
use Flux\Flux;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public const MAX_STORAGE_BYTES = 500_000_000;
    public const PAGE_LIMIT = 8;

    public $files;
    public int $countAllFiles = 0;
    public bool $loading = true;
    public $id;

    public array $folderCounts = [
        'all' => 0,
        'images' => 0,
        'pdf' => 0,
        'excel' => 0,
        'docs' => 0,
    ];

    public string $search = '';
    public string $sort = 'desc';
    public ?string $type = null;
    public ?string $folder = null;
    public int $limit = 8;

    public $selectId;

    public ?int $deletingId = null;
    public string $deletingName = '';

    public FilesForm $form;

    public function placeholder()
    {
        return view('components.placeholder.ph_project_files_tabs');
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) config('services.api_project'), '/').'/'.ltrim($path, '/');
    }

    protected function fileBaseUrl(): string
    {
        return rtrim((string) config('services.url_project'), '/');
    }

    protected function fileUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }
        return $this->fileBaseUrl().'/'.ltrim($path, '/');
    }

    protected function refreshFolderCounts(): void
    {
        $response = Http::timeout(120)->retry(3, 200)->get($this->endpoint('admin-docs/search'), [
            'project_id' => $this->id,
            'limit'      => 10000,
            'sortBy'     => 'created_at',
            'sortOrder'  => 'desc',
        ])->json();

        $all = collect($response['data'] ?? []);
        $extOf = fn ($f) => strtolower(Str::afterLast(data_get($f, 'files.url', ''), '.'));

        $this->folderCounts = [
            'all'    => $all->count(),
            'images' => $all->filter(fn ($f) => in_array($extOf($f), ['jpg', 'jpeg', 'png', 'gif', 'webp']))->count(),
            'pdf'    => $all->filter(fn ($f) => $extOf($f) === 'pdf')->count(),
            'excel'  => $all->filter(fn ($f) => in_array($extOf($f), ['xls', 'xlsx']))->count(),
            'docs'   => $all->filter(fn ($f) => in_array($extOf($f), ['doc', 'docx']))->count(),
        ];
    }

    protected function fetchFiles(int $limit): array
    {
        $response = Http::timeout(120)->retry(3, 200)->get($this->endpoint('admin-docs/search'), [
            'project_id'     => $this->id,
            'limit'          => $limit,
            'extension_type' => $this->type,
            'title'          => $this->search,
            'sortBy'         => 'created_at',
            'sortOrder'      => $this->sort,
        ])->json();

        if (($response['status'] ?? null) !== 200) {
            return ['ok' => false, 'data' => [], 'total' => 0];
        }

        return [
            'ok'    => true,
            'data'  => $response['data'] ?? [],
            'total' => $response['pagination']['total'] ?? 0,
        ];
    }

    #[On('documentLoad')]
    public function mount(): void
    {
        $result = $this->fetchFiles(self::PAGE_LIMIT);

        if (! $result['ok']) {
            Toaster::error('Gagal memuat dokumen');
            $this->files = [];
            $this->loading = false;
            return;
        }

        $this->files = $result['data'];
        $this->countAllFiles = $result['total'];
        $this->refreshFolderCounts();
        $this->loading = false;
    }

    public function applyFilters(): void
    {
        $result = $this->fetchFiles($this->limit);

        if (! $result['ok']) {
            Toaster::error('Gagal memuat dokumen');
            $this->files = [];
            return;
        }

        $this->files = $result['data'];
        $this->countAllFiles = $result['total'];
    }

    public function updatedSearch(): void
    {
        $this->applyFilters();
    }

    public function updatedSort(): void
    {
        $this->applyFilters();
    }

    public function updatedType(): void
    {
        $this->applyFilters();
    }

    public function updatedFolder(): void
    {
        $this->applyFilters();
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_LIMIT;
        $this->applyFilters();
    }

    public function getSelectedFileProperty()
    {
        return collect($this->files)->firstWhere('id', $this->selectId[0] ?? null);
    }

    public function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2).' GB';
        }
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }
        return "{$bytes} B";
    }

    public function getSizeProperty(): float|int
    {
        return collect($this->files)->sum(function ($file) {
            $size = data_get($file, 'files.size');
            preg_match('/([\d\.]+)\s*(KB|MB|GB|B)/i', (string) $size, $match);

            $value = (float) ($match[1] ?? 0);
            $unit = strtoupper($match[2] ?? 'B');

            return match ($unit) {
                'GB'    => $value * 1024 * 1024 * 1024,
                'MB'    => $value * 1024 * 1024,
                'KB'    => $value * 1024,
                default => $value,
            };
        });
    }

    public function resetViewModal(): void
    {
        $this->resetErrorBag();
        $this->reset([
            'selectId',
            'form.title',
            'form.category',
            'form.file',
            'form.uploadedFilename',
            'form.originalName',
        ]);
        $this->dispatch('upload-form-reset');
    }

    public function getStoragePercentProperty(): float
    {
        return min(($this->getSizeProperty() / self::MAX_STORAGE_BYTES) * 100, 100);
    }

    public function getCategoryProperty(): array
    {
        $response = Http::timeout(120)->retry(3, 200)->get($this->endpoint('admin-doc-categories'), ['limit' => 1000])->json();
        return $response['data'] ?? [];
    }

    public function finalizeChunkUpload(array $payload): array
    {
        $data = Validator::make($payload, [
            'title'                 => ['required', 'string', 'min:5'],
            'admin_doc_category_id' => ['required', 'integer'],
            'filename'              => ['required', 'string'],
            'original_name'         => ['required', 'string'],
        ])->validate();

        $response = Http::timeout(120)->post($this->endpoint('admin-docs'), [
            'title'                 => $data['title'],
            'admin_doc_category_id' => $data['admin_doc_category_id'],
            'project_id'            => $this->id,
            'filename'              => $data['filename'],
            'file'                  => $data['filename'],
            'original_name'         => $data['original_name'],
        ])->json();
        if (($response['status'] ?? null) === 201) {
            Toaster::success('File berhasil diupload');
            try {
                $this->mount();
            } catch (\Throwable) {
                // non-blocking
            }
        } else {
           Toaster::error(collect($response['errors'] ?? [])
                    ->flatten()
                    ->join("\n")
            );
        }

        return $response;
    }

    public function confirmDelete(int $id): void
    {
        $item = collect($this->files)->firstWhere('id', $id);

        if (! $item) {
            Toaster::error('File tidak ditemukan');
            return;
        }

        $this->deletingId = $id;
        $this->deletingName = $item['title'] ?? '';
        Flux::modal('delete-file-modal')->show();
    }

    public function fileDelete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $id = $this->deletingId;
        $response = $this->form->delete($id);

        if (($response['status'] ?? null) === 200) {
            $this->files = collect($this->files)
                ->reject(fn (array $file) => $file['id'] === $id)
                ->values()
                ->all();
            $this->countAllFiles = max(0, $this->countAllFiles - 1);
            $this->reset('deletingId', 'deletingName');
            Flux::modal('delete-file-modal')->close();
            $this->mount();
            Toaster::success('File berhasil dihapus');
            return;
        }

        Toaster::error('Hapus file gagal');
        Log::error('File delete failed', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        $this->reset('deletingId', 'deletingName');
        Flux::modal('delete-file-modal')->close();
    }
}; ?>

<div>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    @if(! $this->loading)
        {{-- ============ STORAGE HEADER ============ --}}
        <div class="bg-white border border-zinc-200 rounded-2xl p-5 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center ring-1 ring-red-100">
                        <flux:icon.cloud class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="font-semibold text-zinc-900">Penyimpanan File</flux:heading>
                        <flux:text class="text-sm text-zinc-500">
                            {{ $this->formatBytes($this->size) }} dari {{ $this->formatBytes(500_000_000) }} terpakai
                            <span class="mx-1.5 text-zinc-300">•</span>
                            {{ $countAllFiles }} dokumen
                        </flux:text>
                    </div>
                </div>

                <flux:modal.trigger name="upload-file-modal">
                    <flux:button
                        icon="cloud-arrow-up"
                        variant="primary"
                        x-on:click="window.dispatchEvent(new CustomEvent('upload-modal-opened'));"
                    >
                        Upload File
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mt-4">
                <div class="w-full h-2 bg-zinc-100 rounded-full overflow-hidden">
                    <div class="h-full bg-linear-to-r from-red-500 to-red-600 rounded-full transition-all"
                         style="width: {{ $this->storagePercent }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-zinc-500">
                    {{ number_format($this->storagePercent, 1) }}% terpakai
                </p>
            </div>
        </div>

        {{-- ============ MAIN GRID ============ --}}
        <div x-data="{ folder: 'All Files' }" class="grid lg:grid-cols-4 grid-cols-1 gap-6">

            {{-- ============ FOLDER SIDEBAR ============ --}}
            <aside class="bg-white border border-zinc-200 rounded-2xl p-4 h-fit">
                <flux:heading size="sm" class="font-semibold text-zinc-900 px-2 mb-3">Folder</flux:heading>

                <nav class="space-y-1">
                    @php
                        $folders = [
                            ['key' => 'All Files',   'type' => '',     'label' => 'Semua File',  'icon' => 'folder',          'count' => $folderCounts['all']],
                            ['key' => 'Photos',      'type' => 'jpg',  'label' => 'Foto',        'icon' => 'photo',           'count' => $folderCounts['images']],
                            ['key' => 'PDF Files',   'type' => 'pdf',  'label' => 'PDF',         'icon' => 'document-text',   'count' => $folderCounts['pdf']],
                            ['key' => 'Excel Files', 'type' => 'xls',  'label' => 'Excel',       'icon' => 'table-cells',     'count' => $folderCounts['excel']],
                            ['key' => 'Docs Files',  'type' => 'docx', 'label' => 'Dokumen',     'icon' => 'document',        'count' => $folderCounts['docs']],
                        ];
                    @endphp

                    @foreach($folders as $f)
                        <button
                            type="button"
                            @click="folder = '{{ $f['key'] }}'; $wire.set('type', '{{ $f['type'] }}')"
                            :class="folder === '{{ $f['key'] }}' ? 'bg-red-50 text-red-600 ring-1 ring-red-100' : 'text-zinc-700 hover:bg-zinc-50'"
                            class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg transition cursor-pointer text-sm font-medium"
                        >
                            <span class="flex items-center gap-3 min-w-0">
                                <flux:icon name="{{ $f['icon'] }}" class="w-4 h-4 shrink-0" />
                                <span class="truncate">{{ $f['label'] }}</span>
                            </span>
                            <span :class="folder === '{{ $f['key'] }}' ? 'bg-white text-red-600' : 'bg-zinc-100 text-zinc-500'"
                                  class="text-[11px] font-semibold px-1.5 py-0.5 rounded-md min-w-6 text-center">
                                {{ $f['count'] }}
                            </span>
                        </button>
                    @endforeach
                </nav>
            </aside>

            {{-- ============ FILES PANEL ============ --}}
            <div class="lg:col-span-3 bg-white border border-zinc-200 rounded-2xl">
                {{-- Toolbar --}}
                <div class="px-5 py-4 border-b border-zinc-200">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <h2 class="text-base font-semibold text-zinc-900" x-text="folder"></h2>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="w-44">
                                <flux:select wire:model.live="sort" size="sm">
                                    <flux:select.option value="desc">Terbaru</flux:select.option>
                                    <flux:select.option value="asc">Terlama</flux:select.option>
                                </flux:select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari nama file..."
                            icon="magnifying-glass"
                            clearable />
                    </div>
                </div>

                {{-- File List --}}
                <div class="p-4">
                    {{-- Loading --}}
                    <div wire:loading wire:target="type, search, sort, loadMore" class="space-y-2">
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-zinc-50 animate-pulse">
                                <div class="w-10 h-10 bg-zinc-200 rounded-lg"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-3 w-100 bg-zinc-200 rounded"></div>
                                    <div class="h-2 w-1/4 bg-zinc-100 rounded"></div>
                                </div>
                            </div>
                    </div>

                    <div wire:loading.remove wire:target="type, search, sort, loadMore" class="space-y-1.5">
                        @forelse ($this->files as $item)
                            @php
                                $ext = strtolower(Str::afterLast($item['files']['url'] ?? '', '.'));
                                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                $extStyles = match($ext) {
                                    'pdf'        => ['bg' => 'bg-red-50',     'text' => 'text-red-600',     'label' => 'PDF'],
                                    'doc','docx' => ['bg' => 'bg-blue-50',    'text' => 'text-blue-600',    'label' => 'DOC'],
                                    'xls','xlsx' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'label' => 'XLS'],
                                    'jpg','jpeg','png','gif','webp' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'label' => 'IMG'],
                                    default      => ['bg' => 'bg-zinc-100',   'text' => 'text-zinc-600',    'label' => strtoupper($ext)],
                                };
                            @endphp
                            <div wire:key="file-{{ $item['id'] }}"
                                 class="group flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-transparent hover:border-zinc-200 hover:bg-zinc-50/60 transition">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-[10px] font-bold {{ $extStyles['bg'] }} {{ $extStyles['text'] }} ring-1 ring-inset ring-current/10">
                                        {{ $extStyles['label'] }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-zinc-900 truncate">
                                            {{ $item['title'] }}<span class="text-zinc-400">.{{ $ext }}</span>
                                        </p>
                                        <p class="text-xs text-zinc-500 truncate">
                                            {{ \Carbon\Carbon::parse($item['created_at'])->locale('id')->translatedFormat('d M Y') }}
                                            <span class="mx-1 text-zinc-300">•</span>
                                            {{ $item['files']['size'] ?? '-' }}
                                            @if(!empty($item['admin_doc_category_name']))
                                                <span class="mx-1 text-zinc-300">•</span>
                                                {{ $item['admin_doc_category_name'] }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                    <flux:modal.trigger name="viewModal">
                                        <flux:button
                                            wire:click="$set('selectId', [{{ $item['id'] }}])"
                                            variant="ghost"
                                            size="xs"
                                            icon="eye"
                                            tooltip="Lihat" />
                                    </flux:modal.trigger>

                                    <flux:dropdown wire:key="file-menu-{{ $item['id'] }}">
                                        <flux:button variant="ghost" size="xs" icon="ellipsis-vertical" />
                                        <flux:navmenu>
                                            <flux:modal.trigger name="viewModal">
                                                <flux:navmenu.item icon="eye"
                                                    wire:click="$set('selectId', [{{ $item['id'] }}])">
                                                    Lihat Preview
                                                </flux:navmenu.item>
                                            </flux:modal.trigger>
                                            <flux:navmenu.item icon="arrow-down-tray"
                                                href="{{ $this->fileUrl($item['files']['url'] ?? '') }}"
                                                target="_blank">
                                                Download
                                            </flux:navmenu.item>
                                            <flux:navmenu.separator />
                                            <flux:navmenu.item icon="trash" variant="danger"
                                                wire:click="confirmDelete({{ $item['id'] }})">
                                                Hapus
                                            </flux:navmenu.item>
                                        </flux:navmenu>
                                    </flux:dropdown>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-12">
                                <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center">
                                    <flux:icon.document class="w-6 h-6 text-zinc-400" />
                                </div>
                                <flux:heading size="sm" class="mt-3 text-zinc-900">
                                    {{ $search ? 'Tidak ada hasil' : 'Belum ada file' }}
                                </flux:heading>
                                <flux:text class="text-xs text-zinc-500 mt-1">
                                    {{ $search ? 'Coba kata kunci lain' : 'Upload file pertama untuk memulai' }}
                                </flux:text>
                                @unless($search)
                                    <flux:modal.trigger name="upload-file-modal">
                                        <flux:button variant="primary" icon="cloud-arrow-up" size="sm" class="mt-4"
                                            x-on:click="window.dispatchEvent(new CustomEvent('upload-modal-opened'));">
                                            Upload File
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endunless
                            </div>
                        @endforelse

                        @if($this->limit <= $this->countAllFiles && count($this->files ?? []) > 0)
                            <div class="flex items-center justify-center pt-4">
                                <flux:button variant="outline" size="sm" wire:click="loadMore"
                                    wire:loading.remove wire:target="folder, type, search, sort">
                                    <span wire:loading.remove wire:target="loadMore,applyFilters">Muat Lebih Banyak</span>
                                    <span class="animate-pulse" wire:loading wire:target="loadMore,applyFilters">Memuat...</span>
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- Initial loading skeleton --}}
        <div class="space-y-4">
            <div class="bg-white border border-zinc-200 rounded-2xl p-5 animate-pulse">
                <div class="h-12 bg-zinc-100 rounded-lg"></div>
            </div>
            <div class="grid lg:grid-cols-4 gap-6">
                <div class="bg-white border border-zinc-200 rounded-2xl p-4 h-64 animate-pulse"></div>
                <div class="lg:col-span-3 bg-white border border-zinc-200 rounded-2xl p-6 h-96 animate-pulse"></div>
            </div>
        </div>
    @endif

    {{-- ============ VIEW MODAL ============ --}}
    <flux:modal name="viewModal" wire:close="resetViewModal" class="!max-w-[900px]">
        @php
            $viewFile = $this->selectedFile;
            $viewExt  = $viewFile ? strtolower(Str::afterLast($viewFile['files']['url'], '.')) : null;
            $viewUrl  = $viewFile ? $this->fileUrl($viewFile['files']['url'] ?? '') : null;
            $isImage  = in_array($viewExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            $isPdf    = $viewExt === 'pdf';
        @endphp

        <div wire:loading wire:target="selectId" class="space-y-4 animate-pulse">
            <div class="h-5 w-48 rounded bg-zinc-200"></div>
            <div class="h-125 rounded-xl bg-zinc-100"></div>
        </div>

        <div wire:loading.remove wire:target="selectId" class="space-y-5">
            @if($viewFile)
                <div class="flex items-start justify-between gap-4 pe-6">
                    <div>
                        <flux:heading size="lg" class="truncate font-semibold text-zinc-900">
                            {{ $viewFile['title'] }}<span class="text-zinc-400 font-normal">.{{ $viewExt }}</span>
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            {{ $viewFile['admin_doc_category_name'] ?? 'Tanpa kategori' }}
                            @if(!empty($viewFile['files']['size']))
                                <span class="mx-1 text-zinc-300">•</span>{{ $viewFile['files']['size'] }}
                            @endif
                            @if(!empty($viewFile['created_at']))
                                <span class="mx-1 text-zinc-300">•</span>{{ \Carbon\Carbon::parse($viewFile['created_at'])->locale('id')->translatedFormat('d M Y') }}
                            @endif
                        </flux:text>
                    </div>
                </div>

                @if($isPdf)
                    <iframe src="{{ $viewUrl }}" class="w-full rounded-xl border border-zinc-200 h-150" frameborder="0"></iframe>
                @elseif($isImage)
                    <div class="flex items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 p-4 min-h-75">
                        <img src="{{ $viewUrl }}" alt="{{ $viewFile['title'] }}"
                             class="max-h-140 max-w-full rounded-lg object-contain shadow-sm" loading="lazy" />
                    </div>
                @else
                    <div class="flex h-75 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-200 bg-zinc-50">
                        <div class="grid h-14 w-14 place-items-center rounded-xl bg-white shadow-sm ring-1 ring-zinc-200">
                            <flux:icon.document class="h-7 w-7 text-zinc-400" />
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-medium text-zinc-700">Preview tidak tersedia</p>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                Format <span class="font-semibold text-zinc-700">.{{ strtoupper($viewExt) }}</span> tidak dapat ditampilkan
                            </p>
                        </div>
                        <flux:button :href="$viewUrl" target="_blank" variant="primary" size="sm" icon="arrow-down-tray" class="mt-1">
                            Download File
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>

    {{-- ============ UPLOAD MODAL ============ --}}
    <flux:modal id="upload-file-modal" name="upload-file-modal" wire:close="resetViewModal" class="overflow-visible max-w-xl w-xl" enctype="multipart/form-data">
        <form
            class="space-y-5"
            x-data="projectFilesChunkUploader({{ (int) $this->id }}, @js(route('project-files.upload-chunk')))"
            x-on:submit.prevent="start()"
            x-on:upload-form-reset.window="error = null; hint = null; selectedFile = null"
        >
            <div class="space-y-1">
                <flux:heading size="lg" class="font-semibold text-zinc-900">Upload File</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Tambahkan dokumen baru ke proyek ini.
                </flux:text>
            </div>

            <flux:field>
                <flux:label badge="Wajib">Judul Dokumen</flux:label>
                <flux:input wire:model="form.title" placeholder="cth. Berita Acara Serah Terima" />
                @error('form.title')
                    <flux:error message="{{ $message }}" />
                @enderror
            </flux:field>

            <flux:field>
                <flux:label badge="Wajib">Kategori</flux:label>
                <x-search-select
                    model="form.category"
                    :options="collect($this->category)->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()"
                    placeholder="Pilih kategori..."
                    search-placeholder="Cari kategori..."
                />
                @error('form.category')
                    <flux:error message="{{ $message }}" />
                @enderror
            </flux:field>

            {{-- Drag & drop file area --}}
            <flux:field>
                <flux:label badge="Wajib">File</flux:label>
                <label
                    for="file_input"
                    class="relative flex flex-col items-center justify-center gap-2 px-6 py-8 rounded-xl border-2 border-dashed cursor-pointer transition max-w-full overflow-hidden"
                    :class="selectedFile ? 'border-red-200 bg-red-50/40' : 'border-zinc-200 bg-zinc-50 hover:border-red-200 hover:bg-red-50/30'"
                    @dragover.prevent="$el.classList.add('border-red-300','bg-red-50')"
                    @dragleave.prevent="$el.classList.remove('border-red-300','bg-red-50')"
                    @drop.prevent="
                        $el.classList.remove('border-red-300','bg-red-50');
                        const f = $event.dataTransfer.files?.[0];
                        if (f) { $refs.fileInput.files = $event.dataTransfer.files; selectedFile = f; }
                    "
                >
                    <input
                        id="file_input"
                        x-ref="fileInput"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif"
                        class="sr-only"
                        @change="selectedFile = $event.target.files?.[0] ?? null"
                    >

                    <template x-if="!selectedFile">
                        <div class="flex flex-col items-center gap-2 text-center">
                            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center ring-1 ring-zinc-200">
                                <flux:icon.cloud-arrow-up class="w-5 h-5 text-zinc-500" />
                            </div>
                            <p class="text-sm font-medium text-zinc-700">
                                Klik atau drop file di sini
                            </p>
                            <p class="text-xs text-zinc-500">
                                PDF, DOC, XLS, PNG, JPG (max 2 GB)
                            </p>
                        </div>
                    </template>

                    <template x-if="selectedFile">
                        <div class="flex items-center gap-3 w-full min-w-0 overflow-hidden">
                            <div class="shrink-0 w-10 h-10 rounded-lg bg-red-100 text-red-600 flex items-center justify-center">
                                <flux:icon.document class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0 overflow-hidden">
                                <p
                                    class="block max-w-full truncate text-sm font-medium text-zinc-900"
                                    x-text="selectedFile.name"
                                    :title="selectedFile.name"
                                ></p>

                                <p
                                    class="text-xs text-zinc-500"
                                    x-text="(selectedFile.size / 1024 / 1024).toFixed(2) + ' MB'"
                                ></p>
                            </div>
                            <button
                                type="button"
                                @click.prevent.stop="selectedFile = null; $refs.fileInput.value = ''"
                                class="shrink-0 text-xs text-red-600 hover:text-red-700 font-medium px-2 py-1 rounded hover:bg-red-100"
                            >
                                Ganti
                            </button>
                        </div>
                    </template>
                </label>
                @error('form.file')
                    <flux:error message="{{ $message }}" />
                @enderror
            </flux:field>

            {{-- Error / hint --}}
            <div class="space-y-2" x-cloak x-show="error || hint">
                <div x-show="hint" class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-xs text-blue-700" x-text="hint"></div>
                <div x-show="error" class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700" x-text="error"></div>
            </div>

            <div class="flex gap-2 pt-1">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="flex-1">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="cloud-arrow-up" type="submit" class="flex-1">
                    Upload
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ============ BACKGROUND UPLOAD POPUP ============ --}}
    <div x-cloak x-data class="fixed bottom-4 right-4 z-50 w-80 space-y-2">
        <template x-for="item in ($store.projectFileUploads?.items ?? [])" :key="item.id">
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-lg">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-zinc-800" x-text="item.name"></div>
                        <div class="mt-0.5 text-[11px] text-zinc-500" x-text="item.statusText"></div>
                    </div>
                    <button x-show="item.status === 'uploading' || item.status === 'finalizing'" class="text-[11px] text-red-600 hover:underline shrink-0" @click="$store.projectFileUploads.cancel(item.id)">
                        Cancel
                    </button>
                    <button x-show="item.status !== 'uploading' && item.status !== 'finalizing'" class="text-[11px] text-zinc-600 hover:underline shrink-0" @click="$store.projectFileUploads.dismiss(item.id)">
                        Close
                    </button>
                </div>
                <div class="mt-2 h-2 w-full rounded-full bg-zinc-100 overflow-hidden">
                    <div
                        class="h-2 rounded-full transition-all"
                        :class="item.status === 'error' ? 'bg-red-500' : (item.status === 'done' ? 'bg-emerald-500' : 'bg-red-600')"
                        :style="`width: ${item.progress}%`"
                    ></div>
                </div>
                <div class="mt-1 flex items-center justify-between text-[11px] text-zinc-500">
                    <span x-text="item.progress + '%'"></span>
                    <span x-show="item.status === 'done'" class="text-emerald-600">Done</span>
                    <span x-show="item.status === 'error'" class="text-red-600">Failed</span>
                    <span x-show="item.status === 'canceled'" class="text-zinc-600">Canceled</span>
                </div>
            </div>
        </template>
    </div>

    {{-- ============ DELETE CONFIRMATION MODAL ============ --}}
    <x-confirm-modal name="delete-file-modal" confirm="fileDelete" title="Hapus File?">
        File <span class="font-medium text-zinc-800">"{{ $deletingName }}"</span>
        akan dihapus permanen beserta seluruh datanya. Tindakan ini tidak dapat dibatalkan.
    </x-confirm-modal>
</div>

@script
<script>
    const __wire = $wire;

    // ── Upload store (persistent across modal open/close) ─────────────────────
    const initProjectFileUploadsStore = () => {
        if (!window.Alpine || typeof Alpine.store !== 'function') return;
        if (Alpine.store('projectFileUploads')) return;

        Alpine.store('projectFileUploads', {
            items: [],

            add(item) {
                this.items = [item, ...this.items];
            },

            update(id, patch) {
                const terminal = ['done', 'error', 'canceled'];
                this.items = this.items.map((it) => {
                    if (it.id !== id) return it;
                    const next = { ...it, ...patch };
                    if (!next._dismissTimer && terminal.includes(next.status)) {
                        const delay = next.status === 'done' ? 4000 : 8000;
                        next._dismissTimer = setTimeout(() => this.dismiss(id), delay);
                    }
                    return next;
                });
            },

            cancel(id) {
                const item = this.items.find((it) => it.id === id);
                try { item?.controller?.abort(); } catch (_) {}
            },

            dismiss(id) {
                const item = this.items.find((it) => it.id === id);
                try { if (item?._dismissTimer) clearTimeout(item._dismissTimer); } catch (_) {}
                this.items = this.items.filter((it) => it.id !== id);
            },

            hasActiveUploads() {
                return this.items.some((it) => it.status === 'uploading' || it.status === 'finalizing');
            },
        });
    };

    if (window.Alpine) {
        initProjectFileUploadsStore();
    } else {
        document.addEventListener('alpine:init', initProjectFileUploadsStore);
    }

    // ── Warn user before leaving page while upload is in progress ─────────────
    if (!window._projectFilesBeforeUnloadAdded) {
        window._projectFilesBeforeUnloadAdded = true;
        window.addEventListener('beforeunload', (e) => {
            const hasActive = Alpine?.store?.('projectFileUploads')?.hasActiveUploads?.() ?? false;
            if (hasActive) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    // ── Chunk uploader Alpine component ───────────────────────────────────────
    window.projectFilesChunkUploader = (uploadId, chunkUrl) => ({
        error: null,
        hint: null,
        selectedFile: null,

        async start() {
            this.error = null;
            this.hint = null;

            const file = this.$refs.fileInput?.files?.[0] ?? null;

            const formTitle    = __wire.get('form.title');
            const formCategory = __wire.get('form.category');

            if (!formTitle || String(formTitle).trim().length < 5) {
                this.error = 'Title minimal 5 karakter.';
                return;
            }
            if (!formCategory) {
                this.error = 'Category wajib dipilih.';
                return;
            }
            if (!file) {
                this.error = 'Pilih file terlebih dahulu.';
                return;
            }

            try { $flux.modal('upload-file-modal').close(); } catch (_) {}

            const csrf =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
                document.querySelector('input[name="_token"]')?.value ??
                '';

            const chunkSize   = 2 * 1024 * 1024;
            const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
            let filename       = null;
            const taskId       = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
            const controller   = new AbortController();

            const snapshot = { title: formTitle, category: formCategory };
            try {
                __wire.set('form.title', '');
                __wire.set('form.category', '');
                __wire.set('form.file', null);
                __wire.set('form.uploadedFilename', null);
                __wire.set('form.originalName', null);
            } catch (_) {}
            try { this.$refs.fileInput.value = ''; this.selectedFile = null; } catch (_) {}

            initProjectFileUploadsStore();
            Alpine.store('projectFileUploads')?.add({
                id: taskId,
                name: file.name,
                progress: 0,
                status: 'uploading',
                statusText: `Chunk 0 / ${totalChunks}`,
                controller,
            });

            try {
                for (let i = 0; i < totalChunks; i++) {
                    const start = i * chunkSize;
                    const blob  = file.slice(start, Math.min(file.size, start + chunkSize));

                    Alpine.store('projectFileUploads')?.update(taskId, {
                        statusText: `Uploading ${i + 1} / ${totalChunks}`,
                    });

                    const formData = new FormData();
                    formData.append('upload_id',    String(uploadId));
                    formData.append('chunk_index',  String(i + 1));
                    formData.append('total_chunks', String(totalChunks));
                    formData.append('original_name', file.name);
                    formData.append('file',          blob, file.name);

                    const res  = await fetch(chunkUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body: formData,
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });

                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        throw new Error(json?.message || json?.error || `Upload chunk gagal (HTTP ${res.status}).`);
                    }

                    console.log('Chunk upload response:', json);

                    const candidate =
                        json?.filename ??
                        json?.data?.filename ??
                        json?.data?.file_name ??
                        json?.file ??
                        json?.data?.file ??
                        null;

                    if (typeof candidate === 'string' && candidate.length) {
                        filename = candidate;
                    }

                    Alpine.store('projectFileUploads')?.update(taskId, {
                        progress: Math.round(((i + 1) / totalChunks) * 100),
                    });
                }

                if (!filename) {
                    throw new Error('Upload selesai tapi filename tidak ditemukan dari response server.');
                }

                Alpine.store('projectFileUploads')?.update(taskId, {
                    status: 'finalizing',
                    statusText: 'Menyimpan data dokumen…',
                    progress: 100,
                });

                const finalizeResponse = await __wire.call('finalizeChunkUpload', {
                    title:                 snapshot.title,
                    admin_doc_category_id: Number(snapshot.category),
                    filename:              filename,
                    original_name:         file.name,
                });

                if (!finalizeResponse || finalizeResponse.status !== 201) {

                    let message = finalizeResponse?.message || 'Gagal menyimpan data dokumen.';

                    if (finalizeResponse?.errors) {
                        message = Object.values(finalizeResponse.errors)
                            .flat()
                            .join('\n');
                    }

                    throw new Error(message);
                }

                Alpine.store('projectFileUploads')?.update(taskId, {
                    status:     'done',
                    statusText: 'Selesai',
                    progress:   100,
                    controller: null,
                });
            } catch (e) {
                if (e?.name === 'AbortError') {
                    Alpine.store('projectFileUploads')?.update(taskId, {
                        status:     'canceled',
                        statusText: 'Dibatalkan',
                        controller: null,
                    });
                } else {
                    Alpine.store('projectFileUploads')?.update(taskId, {
                        status:     'error',
                        statusText: e?.message || 'Upload gagal.',
                        controller: null,
                    });
                }
            }
        },
    });
</script>
@endscript

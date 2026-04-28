<?php

use App\Livewire\Forms\FilesForm;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public $files;
    public $countAllFiles = 0;
    public $loading = true;
    public $id;

    public $search = '';
    public $sort = 'desc';
    public $type = null;
    public $folder = null;
    public $limit = 8;

    public $selectId;

    public FilesForm $form;

    #[On('documentLoad')]
    public function mount(): void
    {
        $response = Http::get(
            config('services.api_project') . 'admin-docs/search?project_id=' . $this->id . '&limit=8&sortBy=created_at&sortOrder=' . $this->sort
        )->json();

        if (($response['status'] ?? null) === 200) {
            $this->files = $response['data'];
            $this->countAllFiles = $response['pagination']['total'] ?? 0;
            $this->loading = false;
        } else {
            Toaster::error('Failed to load documents');
            $this->files = [];
        }
    }

    public function applyFilters(): void
    {
        $response = Http::get(
            config('services.api_project') . 'admin-docs/search?project_id=' . $this->id . '&limit=' . $this->limit . '&extension_type=' . $this->type . '&title=' . $this->search . '&sortBy=created_at&sortOrder=' . $this->sort
        )->json();

        if (($response['status'] ?? null) === 200) {
            $this->files = $response['data'];
            $this->countAllFiles = $response['pagination']['total'] ?? 0;
        } else {
            Toaster::error('Failed to load documents');
            $this->files = [];
        }
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
        $this->limit += 8;
        $this->applyFilters();
    }

    public function getSelectedFileProperty()
    {
        return collect($this->files)->firstWhere('id', $this->selectId[0] ?? null);
    }

    public function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getSizeProperty(): float|int
    {
        return collect($this->files)->sum(function ($file) {
            $size = data_get($file, 'files.size');

            preg_match('/([\d\.]+)\s*(KB|MB|GB|B)/i', $size, $match);

            $value = (float) ($match[1] ?? 0);
            $unit  = strtoupper($match[2] ?? 'B');

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
        $max = 500000000;
        return min(($this->getSizeProperty() / $max) * 100, 100);
    }

    public function getCategoryProperty(): array
    {
        $response = Http::get(config('services.api_project') . 'admin-doc-categories?limit=1000')->json();
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

        $response = Http::timeout(120)->post(config('services.api_project') . 'admin-docs', [
            'title'                 => $data['title'],
            'admin_doc_category_id' => $data['admin_doc_category_id'],
            'project_id'            => $this->id,
            'filename'              => $data['filename'],
            'file'                  => $data['filename'],
            'original_name'         => $data['original_name'],
        ])->json();

        if (($response['status'] ?? null) === 201) {
            Toaster::success('File uploaded successfully');
            try {
                $this->mount();
            } catch (\Throwable $e) {
                // non-blocking
            }
        } else {
            Toaster::error('File upload failed');
        }

        return $response;
    }

    public function fileDelete(int $id): void
    {
        $response = $this->form->delete($id);

        if (($response['status'] ?? null) === 200) {
            $this->files = collect($this->files)
                ->reject(fn(array $file) => $file['id'] === $id)
                ->values()
                ->all();
            $this->countAllFiles = max(0, $this->countAllFiles - 1);
            $this->mount();
            Toaster::success('File deleted successfully');
            return;
        }

        Toaster::error('File delete failed');
        \Log::error('File delete failed', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);
    }
}; ?>

<div>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @if(!$this->loading)
        <div class="flex justify-between mb-3 items-center">
            <div class="flex gap-2 items-center">
                <span class="text-lg font-medium text-body">Used Files</span>
                <span class="text-sm text-zinc-500">{{ $this->formatBytes($this->size) }} / 500 MB Used</span>
            </div>
            <div class="flex gap-2">
                <flux:button icon="arrows-up-down" iconVariant="outline" variant="outline"></flux:button>
                <flux:modal.trigger name="upload-file-modal">
                    <flux:button
                        icon="cloud-arrow-up"
                        variant="primary"
                        x-on:click="
                            window.dispatchEvent(new CustomEvent('init-select2-upload'));
                            window.dispatchEvent(new CustomEvent('upload-modal-opened'));
                        "
                    >
                        Upload Files
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="w-full bg-zinc-200 rounded-full h-2">
            <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: {{ $this->storagePercent }}%"></div>
        </div>

        <div x-data="{ files: 'All Files' }" class="grid md:grid-cols-4 grid-cols-1 gap-4 mt-6">
            <div class="w-full min-h-screen">
                <div class="bg-white rounded-lg h-fit">
                    <div class="flex items-center justify-between px-4 py-6 border-b">
                        <h1 class="text-lg font-semibold text-gray-700">Folders</h1>
                    </div>
                    <div class="p-4 space-y-4">
                        <div @click="files = 'All Files'; $wire.set('type','')" :class="files === 'All Files' ? 'border-2 border-blue-400 bg-blue-50' : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 rounded-lg cursor-pointer">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                                </svg>
                                <span :class="files === 'All Files' ? 'text-blue-500 font-medium' : 'text-gray-700'">All Files</span>
                            </div>
                        </div>
                        <div @click="files = 'Photos'; $wire.set('type','jpg')" :class="files === 'Photos' ? 'border-2 border-red-400 bg-red-50' : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 rounded-lg cursor-pointer">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                                </svg>
                                <span :class="files === 'Photos' ? 'text-red-500 font-medium' : 'text-gray-700'">Photos</span>
                            </div>
                        </div>
                        <div @click="files = 'PDF Files'; $wire.set('type','pdf')" :class="files === 'PDF Files' ? 'border-2 border-yellow-400 bg-yellow-50' : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                                </svg>
                                <span :class="files === 'PDF Files' ? 'text-yellow-500 font-medium' : 'text-gray-700'">PDF Files</span>
                            </div>
                        </div>
                        <div @click="files = 'Excel Files'; $wire.set('type','xls')" :class="files === 'Excel Files' ? 'border-2 border-emerald-400 bg-emerald-50' : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                                </svg>
                                <span :class="files === 'Excel Files' ? 'text-emerald-500 font-medium' : 'text-gray-700'">Excel Files</span>
                            </div>
                        </div>
                        <div @click="files = 'Docs Files'; $wire.set('type','docx')" :class="files === 'Docs Files' ? 'border-2 border-blue-400 bg-blue-50' : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                                </svg>
                                <span :class="files === 'Docs Files' ? 'text-blue-500 font-medium' : 'text-gray-700'">Docs Files</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full md:col-span-3 bg-white rounded-lg min-h-screen">
                <div class="pt-3 px-6 border-b border-zinc-200">
                    <div class="flex items-center justify-between mb-6">
                        <h1 class="text-lg font-semibold text-gray-700" x-text="files"></h1>
                        <div class="w-48">
                            <flux:select wire:model.live="sort">
                                <flux:select.option value="desc">Recently Added</flux:select.option>
                                <flux:select.option value="asc">Oldest</flux:select.option>
                            </flux:select>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <input type="text" wire:model.live="search" placeholder="Type to search..." class="w-full border rounded-lg px-4 py-2 text-sm focus:ring focus:ring-blue-200 outline-none">
                    </div>

                    <div class="space-y-2">
                        <div wire:loading wire:target="type, search, sort" class="w-full flex items-center justify-center">
                            <div class="animate-spin w-8 h-8 border-4 mx-auto my-auto border-blue-600 border-t-transparent rounded-full"></div>
                        </div>

                        @forelse ($this->files as $item)
                            @php $ext = Str::afterLast($item['files']['url'], '.'); @endphp
                            <div x-data="{ open: false }" wire:loading.remove wire:target="type, search, sort" class="flex items-center justify-between bg-white border rounded-xl px-4 py-3 hover:bg-gray-50 transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 flex items-center justify-center font-bold rounded-lg text-xs
                                        {{ match($ext) {
                                            'pdf'        => 'bg-red-100 text-red-500',
                                            'doc','docx' => 'bg-blue-100 text-blue-500',
                                            'xls','xlsx' => 'bg-emerald-100 text-emerald-500',
                                            default      => 'bg-gray-100 text-gray-500'
                                        } }}">
                                        {{ match($ext) {
                                            'pdf'        => '.PDF',
                                            'docx','doc' => '.Doc',
                                            'xls','xlsx' => '.Excel',
                                            default      => '.' . strtoupper($ext),
                                        } }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">
                                            {{ $item['title'] . '.' . $ext }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ \Carbon\Carbon::parse($item['created_at'])->locale('id')->translatedFormat('M m, Y') }} • {{ $item['files']['size'] }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        <button @click="open = !open" class="text-gray-500 cursor-pointer hover:text-gray-700">⋯</button>
                                        <div x-show="open" @click.outside="open = false" class="absolute z-10 right-0 mt-2 w-48 bg-white border rounded-lg shadow-lg py-2 text-sm">
                                            <flux:modal.trigger name="viewModal">
                                                <button wire:click="$set('selectId', [{{ $item['id'] }}])" @click="open = false" class="w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-50">View</button>
                                            </flux:modal.trigger>
                                            <button class="w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-50">Share</button>
                                            <a href="{{ env('URL_PROJECT') . ($item['files']['url'] ?? '') }}" target="_blank" download class="block w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-50">Download</a>
                                            <div class="border-t my-2"></div>
                                            <button wire:click="fileDelete({{ $item['id'] }})" wire:loading.attr="disabled" wire:target="fileDelete({{ $item['id'] }})" class="w-full text-left cursor-pointer px-4 py-2 text-red-500 hover:bg-red-50 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="fileDelete({{ $item['id'] }})">Delete</span>
                                                <span wire:loading wire:target="fileDelete({{ $item['id'] }})" class="animate-pulse">Deleting...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p wire:loading.remove wire:target="type, search, sort" class="text-gray-400 text-sm text-center">Tidak ada file</p>
                        @endforelse

                        <div class="flex items-center justify-center h-full">
                            @if($this->limit <= $this->countAllFiles)
                                <flux:button variant="outline" wire:click="loadMore" wire:loading.remove wire:target="folder, type, search, sort">
                                    <span wire:loading.remove wire:target="loadMore,applyFilters">Load More</span>
                                    <span class="animate-pulse" wire:loading wire:target="loadMore,applyFilters">Loading...</span>
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @else
        <div class="text-gray-500 text-sm"></div>
    @endif

    {{-- View modal --}}
    <flux:modal name="viewModal" wire:close="resetViewModal" class="!max-w-[900px]">
        @php
            $viewFile = $this->selectedFile;
            $viewExt  = $viewFile ? strtolower(Str::afterLast($viewFile['files']['url'], '.')) : null;
            $viewUrl  = $viewFile ? env('URL_PROJECT') . ($viewFile['files']['url'] ?? '') : null;
            $isImage  = in_array($viewExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            $isPdf    = $viewExt === 'pdf';
        @endphp

        {{-- Loading skeleton --}}
        <div wire:loading wire:target="selectId" class="space-y-4 animate-pulse">
            <div class="h-5 w-48 rounded bg-gray-200"></div>
            <div class="h-125 rounded-xl bg-gray-100"></div>
        </div>

        {{-- Content --}}
        <div wire:loading.remove wire:target="selectId" class="space-y-4">
            @if($viewFile)
                {{-- Header --}}
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-gray-800">
                            {{ $viewFile['title'] }}<span class="text-gray-400">.{{ $viewExt }}</span>
                        </h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $viewFile['admin_doc_category_name'] ?? '' }}
                            @if(!empty($viewFile['files']['size']))
                                &nbsp;·&nbsp;{{ $viewFile['files']['size'] }}
                            @endif
                        </p>
                    </div>
                    <a
                        href="{{ $viewUrl }}"
                        target="_blank"
                        download
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                        </svg>
                        Download
                    </a>
                </div>

                {{-- Preview area --}}
                @if($isPdf)
                    <iframe
                        src="{{ $viewUrl }}"
                        class="w-full rounded-xl border border-gray-100 h-150"
                        frameborder="0"
                    ></iframe>

                @elseif($isImage)
                    <div class="flex items-center justify-center rounded-xl border border-gray-100 bg-gray-50 p-4 min-h-75">
                        <img
                            src="{{ $viewUrl }}"
                            alt="{{ $viewFile['title'] }}"
                            class="max-h-140 max-w-full rounded-lg object-contain shadow-sm"
                            loading="lazy"
                        />
                    </div>

                @else
                    <div class="flex h-75 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50">
                        <div class="grid h-14 w-14 place-items-center rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                            <svg class="h-7 w-7 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-medium text-gray-600">Preview tidak tersedia</p>
                            <p class="mt-0.5 text-xs text-gray-400">Format <span class="font-semibold">.{{ strtoupper($viewExt) }}</span> tidak dapat ditampilkan</p>
                        </div>
                        <a
                            href="{{ $viewUrl }}"
                            target="_blank"
                            download
                            class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-800"
                        >
                            Download File
                        </a>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>

    {{-- Upload modal --}}
    <flux:modal id="upload-file-modal" name="upload-file-modal" wire:close="resetViewModal" class="overflow-visible w-xl" enctype="multipart/form-data">
        <form
            class="space-y-6"
            x-data="projectFilesChunkUploader({{ (int) $this->id }}, @js(route('project-files.upload-chunk')))"
            x-on:submit.prevent="start()"
            x-on:upload-form-reset.window="error = null; hint = null"
        >
            <flux:field>
                <flux:label>Title</flux:label>
                <flux:input wire:model="form.title" />
                @error('form.title')
                    <flux:error message="{{ $message }}" />
                @enderror
            </flux:field>

            <div wire:ignore>
                <select id="categoryFiles" class="select2 form-select">
                    <option value="">Select a category</option>
                    @foreach ($this->category as $item)
                        <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @error('form.category')
                <flux:error message="{{ $message }}" />
            @enderror

            <div class="space-y-2">
                <label for="file_input" class="block text-sm font-medium text-gray-700">Upload File</label>
                <input
                    id="file_input"
                    x-ref="fileInput"
                    type="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif"
                    class="block w-full text-sm text-gray-700
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-medium
                        file:bg-black file:text-white
                        hover:file:bg-gray-700
                        cursor-pointer border border-gray-300
                        rounded-lg bg-white
                        focus:outline-none focus:ring-2 focus:ring-black focus:border-black"
                >
                <p class="mt-2 text-xs text-gray-500">PDF, PNG, JPG atau GIF (max 2 GB)</p>
                @error('form.file')
                    <flux:error message="{{ $message }}" />
                @enderror
            </div>

            {{-- Error / hint --}}
            <div class="space-y-2" x-cloak x-show="error || hint">
                <div x-show="hint" class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-xs text-blue-700" x-text="hint"></div>
                <div x-show="error" class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700" x-text="error"></div>
            </div>

            <flux:button variant="primary" type="submit" class="w-full">Upload</flux:button>
        </form>
    </flux:modal>

    {{-- Background upload popup --}}
    <div x-cloak x-data class="fixed bottom-4 right-4 z-50 w-80 space-y-2">
        <template x-for="item in ($store.projectFileUploads?.items ?? [])" :key="item.id">
            <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-gray-700" x-text="item.name"></div>
                        <div class="mt-0.5 text-[11px] text-gray-500" x-text="item.statusText"></div>
                    </div>
                    <button x-show="item.status === 'uploading' || item.status === 'finalizing'" class="text-[11px] text-red-600 hover:underline shrink-0" @click="$store.projectFileUploads.cancel(item.id)">
                        Cancel
                    </button>
                    <button x-show="item.status !== 'uploading' && item.status !== 'finalizing'" class="text-[11px] text-gray-600 hover:underline shrink-0" @click="$store.projectFileUploads.dismiss(item.id)">
                        Close
                    </button>
                </div>
                <div class="mt-2 h-2 w-full rounded-full bg-gray-100">
                    <div
                        class="h-2 rounded-full transition-all"
                        :class="item.status === 'error' ? 'bg-red-500' : (item.status === 'done' ? 'bg-emerald-500' : 'bg-blue-500')"
                        :style="`width: ${item.progress}%`"
                    ></div>
                </div>
                <div class="mt-1 flex items-center justify-between text-[11px] text-gray-500">
                    <span x-text="item.progress + '%'"></span>
                    <span x-show="item.status === 'done'" class="text-emerald-600">Done</span>
                    <span x-show="item.status === 'error'" class="text-red-600">Failed</span>
                    <span x-show="item.status === 'canceled'" class="text-gray-600">Canceled</span>
                </div>
            </div>
        </template>
    </div>
</div>

@script
<script>
    const __wire = @this;
    // ── Select2 ───────────────────────────────────────────────────────────────
    const initProjectFilesSelect2 = () => {
        const el = $('#categoryFiles');
        el.select2({
            dropdownParent: $('dialog[data-modal="upload-file-modal"]'),
            width: '100%',
            placeholder: 'Select a category',
            allowClear: true,
        });
        el.on('change', function () {
            @this.set('form.category', $(this).val());
        });
    };

    Livewire.on('initSelect2', () => setTimeout(initProjectFilesSelect2, 0));
    window.addEventListener('init-select2-upload', () => setTimeout(initProjectFilesSelect2, 0));

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
    // @this is captured here (outside Alpine's reactive scope) so Alpine's tracking
    // system never sees the $wire Proxy — preventing the toJSON serialization error.


    window.projectFilesChunkUploader = (uploadId, chunkUrl) => ({
        error: null,
        hint: null,

        async start() {
            this.error = null;
            this.hint = null;

            const file = this.$refs.fileInput?.files?.[0] ?? null;

            // Read form values via the closure-captured wire reference (not this.$wire).
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

            // Close modal immediately; upload continues in the background popup.
            try { $flux.modal('upload-file-modal').close(); } catch (_) {}

            const csrf =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
                document.querySelector('input[name="_token"]')?.value ??
                '';

            const chunkSize   = 2 * 1024 * 1024; // 2 MB
            const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
            let filename       = null;
            const taskId       = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
            const controller   = new AbortController();

            // Snapshot then reset form so the modal is clean for the next upload immediately.
            const snapshot = { title: formTitle, category: formCategory };
            try {
                __wire.set('form.title', '');
                __wire.set('form.category', '');
                __wire.set('form.file', null);
                __wire.set('form.uploadedFilename', null);
                __wire.set('form.originalName', null);
            } catch (_) {}
            try {
                if (window.$) $('#categoryFiles').val(null).trigger('change');
            } catch (_) {}
            try { this.$refs.fileInput.value = ''; } catch (_) {}

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
                        statusText: `Chunk ${i + 1} / ${totalChunks}`,
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
                    throw new Error(finalizeResponse?.message || 'Gagal menyimpan data dokumen.');
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

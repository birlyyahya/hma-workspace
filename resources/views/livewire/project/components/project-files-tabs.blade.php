<?php

use App\Livewire\Forms\FilesForm;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public $files;
    public $allFiles;
    public $loading = true;
    public $id;
    public $filteredTotal = 0;

    public $search = '';
    public $sort = 'latest';
    public $type = null;
    public $folder = null;
    public $limit = 8;

    public $selectId;

    public FilesForm $form;

    #[On('documentLoad')]
    public function filesLoad($files){
        $this->files = collect($files)->take(8)->toArray();
        $this->allFiles = $files;
        $this->loading = false;
        $this->dispatch('initSelect2');
    }


    public function applyFilters()
    {
        $data = collect($this->allFiles);

        // search
        if ($this->search) {
            $data = $data->filter(function ($file) {
                return Str::contains(
                    Str::lower($file['title']),
                    Str::lower($this->search)
                );
            });
        }

        // filter type
        if ($this->type) {
            $data = $data->filter(function ($file) {
                return Str::afterLast($file['files']['url'], '.') === $this->type;
            });
        }

        // filter folder
        if ($this->folder) {
            $data = $data->where('folder', $this->folder);
        }

        // sorting
        if ($this->sort === 'latest') {
            $data = $data->sortByDesc('created_at');
        }

        if ($this->sort === 'oldest') {
            $data = $data->sortBy('created_at');
        }

        if ($this->sort === 'name') {
            $data = $data->sortBy('title');
        }
        $this->filteredTotal = $data->count();
        $this->files = $data->take($this->limit ?? 8)->values()->toArray();
    }

    public function updatedSearch()
    {
        $this->applyFilters();
    }

    public function updatedSort()
    {
        $this->applyFilters();
    }

    public function updatedType()
    {
        $this->applyFilters();
    }

    public function updatedFolder()
    {
        $this->applyFilters();
    }

    public function loadMore()
    {
        $this->limit += 8;
        $this->applyFilters();
    }

    public function getSelectedFileProperty()
    {
        return collect($this->files)->firstWhere('id', $this->selectId[0] ?? null);
    }
    public function formatBytes($bytes)
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
   public function getSizeProperty()
    {
        return collect($this->allFiles)->sum(function ($file) {

            $size = data_get($file, 'files.size');

            preg_match('/([\d\.]+)\s*(KB|MB|GB|B)/i', $size, $match);

            $value = (float) ($match[1] ?? 0);
            $unit  = strtoupper($match[2] ?? 'B');

            return match ($unit) {
                'GB' => $value * 1024 * 1024 * 1024,
                'MB' => $value * 1024 * 1024,
                'KB' => $value * 1024,
                default => $value,
            };
        });
    }

    public function resetViewModal()
    {
        $this->reset(['selectId','form.title','form.category','form.file']);
    }
    public function getStoragePercentProperty()
    {
        $max = 500000000; // 1 GB
        $used = $this->size; // total byte file

        return min(($used / $max) * 100, 100);
    }

    public function getCategoryProperty(){
        $response = Http::get(env('API_PROJECT') . 'admin-doc-categories?limit=1000')->json();
        return $response['data'];
    }

    public function fileUpload(){
         $response = $this->form->store($this->id);
         if ($response['status'] === 201) {

        $this->reset('form');

        Toaster::success('File uploaded successfully');

        } else {
            Toaster::error('File upload failed');

            \Log::error('File API failed', [
                'status' => $response['status'],
                'body'   => $response['message'] ?? 'No message',
            ]);
    }
    }
}; ?>

<div>
    @if(!$this->loading)

    <div class="flex justify-between mb-3 items-center">
        <div class="flex gap-2 items-center">
            <span class="text-lg font-medium text-body">Used Files</span>
            <span class="text-sm text-zinc-500">{{ $this->formatBytes($this->size) }} / 500 MB Used</span>
        </div>
        <div class="flex gap-2">
            <flux:button icon="arrows-up-down" iconVariant="outline" variant="outline"></flux:button>
            <flux:modal.trigger name="upload-file-modal">
                <flux:button icon="cloud-arrow-up" variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('init-select2-upload'))">
                    Upload Files
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>
    <div class="w-full bg-zinc-200 rounded-full h-2">
        <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: {{ $this->storagePercent }}%">
        </div>
    </div>
    <div x-data="{
        files: 'All Files'
    }" class="grid grid-cols-4 gap-4 mt-6">
        <div class="w-full  min-h-screen">
            <div class="bg-white rounded-lg h-fit">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-6 border-b">
                    <h1 class="text-lg font-semibold text-gray-700">
                        Folders
                    </h1>
                </div>
                <!-- Folder List -->
                <div class="p-4 space-y-4">
                    <!-- Active Folder -->
                    <div @click="files = 'All Files'; $wire.set('type','')" :class="files === 'All Files'
        ? 'border-2 border-blue-400 bg-blue-50'
        : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 rounded-lg cursor-pointer">
                        <div class="flex items-center gap-3">
                            <!-- Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                            </svg>
                            <span :class="files === 'All Files' ? 'text-blue-500 font-medium' : 'text-gray-700'">
                                All Files
                            </span>
                        </div>
                    </div>
                    <!-- Folder Item -->
                    <div @click="files = 'Photos'; $wire.set('type','png')" :class="files === 'Photos'
                        ? 'border-2 border-red-400 bg-red-50'
                        : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 rounded-lg cursor-pointer">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                            </svg>
                            <span :class="files === 'Photos' ? 'text-red-500 font-medium' : 'text-gray-700'">
                                Photos
                            </span>
                        </div>
                    </div>
                    <div @click="files = 'PDF Files'; $wire.set('type','pdf')" :class="files === 'PDF Files'
                        ? 'border-2 border-yellow-400 bg-yellow-50'
                        : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                            </svg>

                            <span :class="files === 'PDF Files' ? 'text-yellow-500 font-medium' : 'text-gray-700'">
                                PDF Files
                            </span>
                        </div>
                    </div>
                    <div @click="files = 'Excel Files'; $wire.set('type','xls')" :class="files === 'Excel Files'
                        ? 'border-2 border-emerald-400 bg-emerald-50'
                        : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                            </svg>

                            <span :class="files === 'Excel Files' ? 'text-emerald-500 font-medium' : 'text-gray-700'">
                                Excel Files
                            </span>
                        </div>
                    </div>
                    <div @click="files = 'Docs Files'; $wire.set('type','docx')" :class="files === 'Docs Files'
                        ? 'border-2 border-blue-400 bg-blue-50'
                        : 'border hover:bg-gray-50'" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7h5l2 2h11v10a2 2 0 0 1-2 2H3z" />
                            </svg>

                            <span :class="files === 'Docs Files' ? 'text-blue-500 font-medium' : 'text-gray-700'">
                                Docs Files
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="w-full col-span-3 bg-white rounded-lg min-h-screen">
            <div class="pt-3 px-6 border-b border-zinc-200">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-lg font-semibold text-gray-700" x-text="files">
                    </h1>
                    <!-- Filter -->
                    <div class="w-48">
                        <flux:select wire:model.live="sort">
                            <flux:select.option value="latest">
                                Recently Added
                            </flux:select.option>

                            <flux:select.option value="oldest">
                                Oldest
                            </flux:select.option>

                            <flux:select.option value="name">
                                Name A-Z
                            </flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <!-- Search -->
                <div class="mb-4">
                    <input type="text" wire:model.live="search" placeholder="Type to search..." class="w-full border rounded-lg px-4 py-2 text-sm focus:ring focus:ring-blue-200 outline-none">
                </div>

                <!-- File List -->
                <div class="space-y-2">
                    {{-- Loading --}}
                    <div wire:loading wire:target="type, search, sort"  class="w-full flex items-center justify-center">
                        <div class="animate-spin w-8 h-8 border-4 mx-auto my-auto border-blue-600 border-t-transparent rounded-full"></div>
                    </div>
                    @forelse ($this->files as $item)
                    <!-- File Item -->
                    <div x-data="{open:false}" wire:loading.remove wire:target="type, search, sort" class="flex items-center justify-between bg-white border rounded-xl px-4 py-3 hover:bg-gray-50 transition">

                        <!-- Left -->
                        <div class="flex items-center gap-4">

                            <!-- Icon -->
                            <div class="w-10 h-10 flex items-center justify-center font-bold rounded-lg text-xs
                                {{ match(Str::afterLast($item['files']['url'], '.')) {
                                    'pdf' => 'bg-red-100 text-red-500',
                                    'doc', 'docx' => 'bg-blue-100 text-blue-500',
                                    'xls', 'xlsx' => 'bg-emerald-100 text-emerald-500',
                                    default => 'bg-gray-100 text-gray-500'
                                } }}">
                                @if(Str::afterLast($item['files']['url'], '.') == 'pdf')
                                .PDF
                                @elseif (Str::afterLast($item['files']['url'], '.') == 'docx')
                                .Doc
                                @elseif (Str::afterLast($item['files']['url'], '.') == 'xsl')
                                .Excel
                                @endif
                            </div>

                            <!-- File Info -->
                            <div>
                                <p class="text-sm font-medium text-gray-700">
                                    {{ $item['title'].'.'.Str::afterLast($item['files']['url'], '.') }}
                                </p>

                                <p class="text-xs text-gray-500">
                                    {{ Carbon\Carbon::parse($item['created_at'])->locale('id')->translatedFormat('M m, Y') }} • {{ $item['files']['size'] }}
                                </p>
                            </div>
                        </div>

                        <!-- Right -->
                        <div class="flex items-center gap-4">
                            <!-- Dropdown -->
                            <div class="relative">
                                <button @click="open=!open" class="text-gray-500 cursor-pointer hover:text-gray-700">
                                    ⋯
                                </button>

                                <div x-show="open" @click.outside="open=false" class="absolute z-10 right-0 mt-2 w-48 bg-white border rounded-lg shadow-lg py-2 text-sm">
                                    <flux:modal.trigger name="viewModal">
                                        <button wire:click="$set('selectId', [{{ $item['id'] }}])" @click="open=false" class="w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-50">
                                            View
                                        </button>
                                    </flux:modal.trigger>

                                    <button class="w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-50">
                                        Share
                                    </button>

                                    <a href="{{ env('URL_PROJECT') . ($item['files']['url'] ?? '') }}" target="_blank" download class="block w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-50">
                                        Download
                                    </a>

                                    <div class="border-t my-2"></div>

                                    <button class="w-full text-left cursor-pointer px-4 py-2 text-red-500 hover:bg-red-50">
                                        Delete
                                    </button>
                                </div>
                            </div>

                        </div>

                    </div>
                    @empty
                    <p wire:loading.remove wire:target="type, search, sort"  class="text-gray-400 text-sm text-center">Tidak ada file</p>
                    @endforelse
                    <div class="flex items-center justify-center h-full" wire:loading.remove>
                        @if(count($this->files) < ($this->filteredTotal === 0 ? count($this->allFiles) : $this->filteredTotal) && !empty($this->files))
                        <flux:button variant="outline" wire:click='loadMore'>
                            Load More
                        </flux:button>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>
    @else
    {{-- Skeleton --}}
    <div class="text-gray-500 text-sm">

    </div>
    @endif
    <flux:modal name="viewModal" wire:close='resetViewModal' class="!max-w-[900px]">
        <div class="space-y-6">
            <p>{{ $this->selectedFile['title'] ?? '' }}</p>
            @if($this->selectedFile)
            @if(Str::afterLast($this->selectedFile['files']['url'], '.') == 'pdf')
            <iframe src="{{ env('URL_PROJECT') . data_get($this->selectedFile,'files.url','') }}" class="w-150 h-[600px]" frameborder="0">
            </iframe>
            @else
            <div class="w-150 h-[500px] flex items-center justify-center">
                <flux:text class="text-center">
                    Tidak ada preview untuk file ini
                </flux:text>
            </div>
            @endif
            @endif
            {{-- Loading --}}
            <div wire:loading class="w-140 h-[500px] flex items-center justify-center">
                <div class="animate-spin w-8 h-8 border-4 mx-auto my-auto border-blue-600 border-t-transparent rounded-full"></div>
            </div>
        </div>
    </flux:modal>
    <flux:modal id="upload-file-modal" name="upload-file-modal" wire:close='resetViewModal' class="overflow-visible w-xl" enctype="multipart/form-data">
        <form wire:submit='fileUpload' class="space-y-6">
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
                <label for="file_input" class="block text-sm font-medium text-gray-700">
                    Upload File
                </label>
                <input id="file_input" type="file" wire:model="form.file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif" class="block w-full text-sm text-gray-700
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-medium
                    file:bg-black file:text-white
                    hover:file:bg-gray-700
                    cursor-pointer
                    border border-gray-300
                    rounded-lg
                    bg-white
                    focus:outline-none focus:ring-2 focus:ring-black focus:border-black">
                <p class="mt-2 text-xs text-gray-500">
                    PDF, PNG, JPG or GIF (max 800×400px)
                </p>
                @error('form.file')
                <flux:error message="{{ $message }}" />
                @enderror
            </div>
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Upload') }}</flux:button>
        </form>
    </flux:modal>
</div>
@script
<script>
    const initProjectFilesSelect2 = () => {
        const el = $('#categoryFiles');
        el.select2({
            dropdownParent: $('dialog[data-modal="upload-file-modal"]')
            , width: '100%'
            , placeholder: "Select a category"
            , allowClear: true
        , });


        el.on('change', function() {
            @this.set('form.category', $(this).val());
        });
    };

    Livewire.on('initSelect2', () => {
        setTimeout(initProjectFilesSelect2, 0);
    });

    window.addEventListener('init-select2-upload', () => {
        setTimeout(initProjectFilesSelect2, 0);
    });

</script>
@endscript

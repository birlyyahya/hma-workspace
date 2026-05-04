<?php

use App\Models\SupportDocumentation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public string $search = '';
    public string $activeCategory = 'all';

    public ?int $editingId = null;
    public string $title = '';
    public string $description = '';
    public string $content = '';
    public string $category = '';
    public int $order = 0;
    public bool $isActive = true;
    public $file = null;
    public ?string $existingFile = null;

    public ?int $deletingId = null;
    public ?string $deletingTitle = null;

    public ?int $viewingId = null;

    #[Computed]
    public function categories(): array
    {
        return SupportDocumentation::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    #[Computed]
    public function items()
    {
        return SupportDocumentation::query()
            ->with('author:id,name,username')
            ->when($this->activeCategory !== 'all', fn ($q) => $q->where('category', $this->activeCategory))
            ->when($this->search, fn ($q) => $q->where(function ($qq) {
                $like = '%'.Str::lower($this->search).'%';
                $qq->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
            }))
            ->orderBy('order')
            ->latest()
            ->get();
    }

    #[Computed]
    public function viewing(): ?SupportDocumentation
    {
        return $this->viewingId ? SupportDocumentation::with('author')->find($this->viewingId) : null;
    }

    public function canManage(): bool
    {
        return Gate::allows('create', SupportDocumentation::class);
    }

    public function openCreate(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }
        $this->resetForm();
        Flux::modal('doc-form-modal')->show();
    }

    public function openEdit(int $id): void
    {
        $item = SupportDocumentation::find($id);
        if (! $item || Gate::denies('update', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->description = $item->description ?? '';
        $this->content = $item->content;
        $this->category = $item->category ?? '';
        $this->order = (int) $item->order;
        $this->isActive = (bool) $item->is_active;
        $this->existingFile = $item->file;
        $this->file = null;

        Flux::modal('doc-form-modal')->show();
    }

    public function save(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'order' => ['integer', 'min:0'],
            'isActive' => ['boolean'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'content' => $data['content'],
            'category' => $data['category'] ?: null,
            'order' => $data['order'],
            'is_active' => $data['isActive'],
            'created_by' => Auth::id(),
        ];

        if ($this->file) {
            if ($this->existingFile) {
                Storage::disk('public')->delete($this->existingFile);
            }
            $payload['file'] = $this->file->store('knowledge/documentations', 'public');
        }

        if ($this->editingId) {
            $item = SupportDocumentation::find($this->editingId);
            if (! $item || Gate::denies('update', $item)) {
                Toaster::error('Anda tidak memiliki akses');
                return;
            }
            $item->update($payload);
            Toaster::success('Dokumentasi diperbarui');
        } else {
            SupportDocumentation::create($payload);
            Toaster::success('Dokumentasi dibuat');
        }

        $this->resetForm();
        Flux::modal('doc-form-modal')->close();
    }

    public function confirmDelete(int $id): void
    {
        $item = SupportDocumentation::find($id);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $this->deletingId = $id;
        $this->deletingTitle = $item->title;
        Flux::modal('doc-delete-modal')->show();
    }

    public function delete(): void
    {
        $item = SupportDocumentation::find($this->deletingId);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        if ($item->file) {
            Storage::disk('public')->delete($item->file);
        }

        $item->delete();
        Toaster::success('Dokumentasi dihapus');

        $this->reset('deletingId', 'deletingTitle');
        Flux::modal('doc-delete-modal')->close();
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        Flux::modal('doc-view-modal')->show();
    }

    public function removeExistingFile(): void
    {
        if (! $this->editingId || ! $this->existingFile) {
            return;
        }
        $item = SupportDocumentation::find($this->editingId);
        if (! $item || Gate::denies('update', $item)) {
            return;
        }
        Storage::disk('public')->delete($this->existingFile);
        $item->update(['file' => null]);
        $this->existingFile = null;
        Toaster::success('File dihapus');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'description', 'content', 'category', 'file', 'existingFile']);
        $this->order = 0;
        $this->isActive = true;
        $this->resetErrorBag();
    }
}; ?>

<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-2">{{ __('Support Center') }}</flux:heading>
        <flux:subheading>{{ __('Prosedur, panduan, dan dokumentasi internal') }}</flux:subheading>
        <flux:separator variant="subtle" class="mt-4" />
    </div>

    <x-settings.knowledge-layout
        :heading="__('Documentation & Guides')"
        :subheading="__('Akses prosedur operasional, pedoman, dan dokumentasi internal organisasi.')"
    >
        <x-slot name="action">
            @if ($this->canManage())
                <flux:button icon="plus" variant="primary" wire:click="openCreate">Tambah Dokumentasi</flux:button>
            @endif
        </x-slot>

        <div class="mb-5">
            <flux:input
                icon="magnifying-glass"
                placeholder="Cari dokumentasi..."
                wire:model.live.debounce.300ms="search"
                clearable
            />
        </div>

        {{-- CATEGORY CHIPS --}}
        @if (count($this->categories) > 0)
            <div class="flex flex-wrap items-center gap-2 mb-5">
                <button wire:click="$set('activeCategory', 'all')"
                    class="px-3 py-1.5 text-xs rounded-full border transition
                    {{ $activeCategory === 'all' ? 'bg-emerald-600 text-white border-emerald-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                    Semua
                </button>
                @foreach ($this->categories as $cat)
                    <button wire:click="$set('activeCategory', '{{ addslashes($cat) }}')"
                        class="px-3 py-1.5 text-xs rounded-full border transition
                        {{ $activeCategory === $cat ? 'bg-emerald-600 text-white border-emerald-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                        {{ $cat }}
                    </button>
                @endforeach
            </div>
        @endif

        @if ($this->items->count() === 0)
            <div class="flex flex-col items-center justify-center py-16 px-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center mb-3">
                    <flux:icon name="book-open" class="w-6 h-6 text-emerald-500" />
                </div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Belum ada dokumentasi</p>
            </div>
        @else
            <div class="space-y-6">
                @php($grouped = $this->items->groupBy(fn ($i) => $i->category ?? 'Lainnya'))
                @foreach ($grouped as $catName => $docs)
                    <section wire:key="cat-{{ $catName }}">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-2 px-1">
                            {{ $catName }}
                        </h3>
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($docs as $doc)
                                <div wire:key="doc-{{ $doc->id }}" id="{{ $doc->slug }}"
                                    class="group flex items-center justify-between gap-3 p-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition">
                                    <button wire:click="view({{ $doc->id }})" class="flex items-start gap-3 flex-1 min-w-0 text-left">
                                        <div class="shrink-0 w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                                            <flux:icon name="document-text" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-white truncate group-hover:text-emerald-600">
                                                    {{ $doc->title }}
                                                </p>
                                                @if (! $doc->is_active)
                                                    <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                                @endif
                                            </div>
                                            @if ($doc->description)
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-1 mt-0.5">{{ $doc->description }}</p>
                                            @endif
                                        </div>
                                    </button>

                                    <div class="flex items-center gap-1">
                                        @if ($doc->file)
                                            <a href="{{ Storage::url($doc->file) }}" target="_blank"
                                                class="text-xs text-blue-600 hover:underline inline-flex items-center gap-1 px-2">
                                                <flux:icon name="paper-clip" class="w-3 h-3" /> File
                                            </a>
                                        @endif
                                        @if (Gate::allows('update', $doc) || Gate::allows('delete', $doc))
                                            <flux:dropdown align="end">
                                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" inset />
                                                <flux:menu>
                                                    @can('update', $doc)
                                                        <flux:menu.item icon="pencil" wire:click="openEdit({{ $doc->id }})">Edit</flux:menu.item>
                                                    @endcan
                                                    @can('delete', $doc)
                                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $doc->id }})">Hapus</flux:menu.item>
                                                    @endcan
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                        <flux:icon name="arrow-right" class="w-4 h-4 text-zinc-300 group-hover:text-emerald-500 transition" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </x-settings.knowledge-layout>

    {{-- FORM MODAL --}}
    <flux:modal name="doc-form-modal" class="md:w-[640px]" wire:close="resetForm">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                    <flux:icon name="book-open" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingId ? 'Edit Dokumentasi' : 'Tambah Dokumentasi' }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">SOP, panduan, atau prosedur kerja</flux:text>
                </div>
            </div>

            <flux:input wire:model="title" label="Judul" placeholder="Employee Onboarding Procedure" />
            <flux:textarea wire:model="description" label="Deskripsi (opsional)" rows="2" />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <flux:input wire:model="category" label="Kategori" placeholder="HR, IT, Operations, ..." />
                </div>
                <flux:input type="number" wire:model="order" label="Urutan" min="0" />
            </div>

            <flux:textarea wire:model="content" label="Konten" rows="10" placeholder="Tulis langkah / dokumentasi lengkap..." />

            <flux:switch wire:model="isActive" label="Aktif" />

            <div>
                <flux:text class="text-sm font-medium mb-2 block">Lampiran (opsional, max 10MB)</flux:text>
                <input type="file" wire:model="file" class="block w-full text-sm text-zinc-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-200" />
                @error('file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                @if ($existingFile)
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <flux:icon name="paper-clip" class="w-3.5 h-3.5 text-zinc-400" />
                        <a href="{{ Storage::url($existingFile) }}" target="_blank" class="text-blue-600 hover:underline truncate">{{ basename($existingFile) }}</a>
                        <button wire:click="removeExistingFile" type="button" class="text-red-600 hover:underline">Hapus</button>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button wire:click="save" variant="primary" icon="check"
                    wire:loading.attr="disabled" wire:target="save,file">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan' : 'Tambah' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- DELETE MODAL --}}
    <flux:modal name="doc-delete-modal" class="md:w-110">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600" />
                </div>
                <div class="space-y-1 flex-1">
                    <flux:heading size="lg">Hapus Dokumentasi?</flux:heading>
                    <flux:text class="text-sm text-zinc-500">
                        "<span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $deletingTitle }}</span>" akan dihapus permanen.
                    </flux:text>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:modal.close><flux:button variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash" class="flex-1"
                    wire:loading.attr="disabled" wire:target="delete">
                    <span wire:loading.remove wire:target="delete">Hapus</span>
                    <span wire:loading wire:target="delete">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- VIEW MODAL --}}
    <flux:modal name="doc-view-modal" class="md:w-[720px]">
        @if ($this->viewing)
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center">
                        <flux:icon name="book-open" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        @if ($this->viewing->category)
                            <flux:badge color="emerald" size="sm">{{ $this->viewing->category }}</flux:badge>
                        @endif
                        <flux:heading size="lg" class="mt-1">{{ $this->viewing->title }}</flux:heading>
                        @if ($this->viewing->description)
                            <p class="text-sm text-zinc-500 mt-1">{{ $this->viewing->description }}</p>
                        @endif
                        <p class="text-xs text-zinc-400 mt-1">
                            {{ $this->viewing->author?->name ?? 'System' }} • Updated {{ $this->viewing->updated_at?->format('d M Y, H:i') }}
                        </p>
                    </div>
                </div>

                <flux:separator variant="subtle" />

                <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-zinc-700 dark:text-zinc-300 max-h-[60vh] overflow-y-auto">{{ $this->viewing->content }}</div>

                @if ($this->viewing->file)
                    <a href="{{ Storage::url($this->viewing->file) }}" target="_blank"
                        class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                        <flux:icon name="paper-clip" class="w-4 h-4" /> Buka lampiran
                    </a>
                @endif
            </div>
        @endif
    </flux:modal>
</div>

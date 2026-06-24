<?php

use App\Models\SupportAnnouncement;
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
    public string $filter = 'all';

    public ?int $editingId = null;
    public string $title = '';
    public string $content = '';
    public string $priority = 'normal';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public bool $isActive = true;
    public $file = null;
    public ?string $existingFile = null;

    public ?int $deletingId = null;
    public ?string $deletingTitle = null;

    public ?int $viewingId = null;

    #[Computed]
    public function items()
    {
        return SupportAnnouncement::query()
            ->with('author:id,name,username')
            ->when($this->search, fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%'.Str::lower($this->search).'%']))
            ->when($this->filter === 'active', fn ($q) => $q->active()->ongoing())
            ->when($this->filter === 'important', fn ($q) => $q->where('priority', 'important'))
            ->latest()
            ->get();
    }

    #[Computed]
    public function viewing(): ?SupportAnnouncement
    {
        return $this->viewingId ? SupportAnnouncement::with('author')->find($this->viewingId) : null;
    }

    public function canManage(): bool
    {
        return Gate::allows('create', SupportAnnouncement::class);
    }

    public function openCreate(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }
        $this->resetForm();
        Flux::modal('announcement-form-modal')->show();
    }

    public function openEdit(int $id): void
    {
        $item = SupportAnnouncement::find($id);
        if (! $item || Gate::denies('update', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->content = $item->content;
        $this->priority = $item->priority;
        $this->startDate = $item->start_date?->toDateString();
        $this->endDate = $item->end_date?->toDateString();
        $this->isActive = (bool) $item->is_active;
        $this->existingFile = $item->file;
        $this->file = null;

        Flux::modal('announcement-form-modal')->show();
    }

    public function save(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'priority' => ['required', 'in:normal,important'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'isActive' => ['boolean'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $payload = [
            'title' => $data['title'],
            'content' => $data['content'],
            'priority' => $data['priority'],
            'start_date' => $data['startDate'] ?? null,
            'end_date' => $data['endDate'] ?? null,
            'is_active' => $data['isActive'],
            'created_by' => Auth::id(),
        ];

        if ($this->file) {
            if ($this->existingFile) {
                Storage::delete($this->existingFile);
            }
            $payload['file'] = $this->file->storePublicly('knowledge/announcements', config('filesystems.default'));
        }

        if ($this->editingId) {
            $item = SupportAnnouncement::find($this->editingId);
            if (! $item || Gate::denies('update', $item)) {
                Toaster::error('Anda tidak memiliki akses');
                return;
            }
            $item->update($payload);
            Toaster::success('Pengumuman berhasil diperbarui');
        } else {
            SupportAnnouncement::create($payload);
            Toaster::success('Pengumuman berhasil dibuat');
        }

        $this->resetForm();
        Flux::modal('announcement-form-modal')->close();
    }

    public function confirmDelete(int $id): void
    {
        $item = SupportAnnouncement::find($id);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $this->deletingId = $id;
        $this->deletingTitle = $item->title;
        Flux::modal('announcement-delete-modal')->show();
    }

    public function delete(): void
    {
        $item = SupportAnnouncement::find($this->deletingId);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        if ($item->file) {
            Storage::delete($item->file);
        }

        $item->delete();
        Toaster::success('Pengumuman dihapus');

        $this->reset('deletingId', 'deletingTitle');
        Flux::modal('announcement-delete-modal')->close();
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        Flux::modal('announcement-view-modal')->show();
    }

    public function removeExistingFile(): void
    {
        if (! $this->editingId || ! $this->existingFile) {
            return;
        }
        $item = SupportAnnouncement::find($this->editingId);
        if (! $item || Gate::denies('update', $item)) {
            return;
        }
        Storage::delete($this->existingFile);
        $item->update(['file' => null]);
        $this->existingFile = null;
        Toaster::success('File dihapus');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'content', 'priority', 'startDate', 'endDate', 'file', 'existingFile']);
        $this->priority = 'normal';
        $this->isActive = true;
        $this->resetErrorBag();
    }
}; ?>

<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-2">{{ __('Support Center') }}</flux:heading>
        <flux:subheading>{{ __('Pengumuman & informasi penting') }}</flux:subheading>
        <flux:separator variant="subtle" class="mt-4" />
    </div>

    <x-settings.knowledge-layout
        :heading="__('Announcements')"
        :subheading="__('Stay updated with the latest news, updates, and important notices.')"
    >
        <x-slot name="action">
            @if ($this->canManage())
                <flux:button icon="plus" variant="primary" wire:click="openCreate">Buat Pengumuman</flux:button>
            @endif
        </x-slot>

        <div class="flex flex-col sm:flex-row gap-3 mb-5">
            <flux:input
                icon="magnifying-glass"
                placeholder="Cari pengumuman..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="flex-1"
            />
            <flux:select wire:model.live="filter" class="sm:w-48">
                <option value="all">Semua</option>
                <option value="active">Aktif & Berlaku</option>
                <option value="important">Important</option>
            </flux:select>
        </div>

        @if ($this->items->count() === 0)
            <div class="flex flex-col items-center justify-center py-16 px-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center mb-3">
                    <flux:icon name="megaphone" class="w-6 h-6 text-red-500" />
                </div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Belum ada pengumuman</p>
                <p class="text-xs text-zinc-500 mt-1">Pengumuman akan tampil di dashboard semua user</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->items as $item)
                    <article wire:key="ann-{{ $item->id }}" id="{{ $item->slug }}"
                        class="group rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 hover:shadow-sm transition">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center
                                {{ $item->priority === 'important' ? 'bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' }}">
                                <flux:icon name="{{ $item->priority === 'important' ? 'fire' : 'megaphone' }}" class="w-5 h-5" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button wire:click="view({{ $item->id }})" class="text-sm font-semibold text-zinc-900 dark:text-white hover:text-blue-600 text-left">
                                        {{ $item->title }}
                                    </button>
                                    @if ($item->priority === 'important')
                                        <flux:badge color="red" size="sm">Important</flux:badge>
                                    @endif
                                    @if (! $item->is_active)
                                        <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 mt-1">
                                    {{ Str::limit(strip_tags($item->content), 200) }}
                                </p>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-400 mt-2">
                                    <span>{{ $item->author?->name ?? 'System' }}</span>
                                    <span>•</span>
                                    <span>{{ $item->created_at?->diffForHumans() }}</span>
                                    @if ($item->start_date || $item->end_date)
                                        <span>•</span>
                                        <span>
                                            {{ $item->start_date?->format('d M Y') ?? '...' }} – {{ $item->end_date?->format('d M Y') ?? '...' }}
                                        </span>
                                    @endif
                                    @if ($item->file)
                                        <span>•</span>
                                        <a href="{{ Storage::url($item->file) }}" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1">
                                            <flux:icon name="paper-clip" class="w-3 h-3" /> Lampiran
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if (Gate::allows('update', $item) || Gate::allows('delete', $item))
                                <flux:dropdown align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" inset />
                                    <flux:menu>
                                        @can('update', $item)
                                            <flux:menu.item icon="pencil" wire:click="openEdit({{ $item->id }})">Edit</flux:menu.item>
                                        @endcan
                                        @can('delete', $item)
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $item->id }})">Hapus</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-settings.knowledge-layout>

    {{-- FORM MODAL --}}
    <flux:modal name="announcement-form-modal" class="md:w-[640px]" wire:close="resetForm">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-red-500 to-rose-600 flex items-center justify-center">
                    <flux:icon name="megaphone" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingId ? 'Edit Pengumuman' : 'Buat Pengumuman' }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">Akan tampil di dashboard semua user</flux:text>
                </div>
            </div>

            <flux:input wire:model="title" label="Judul" placeholder="Contoh: Libur Hari Raya Idul Fitri" />
            <flux:textarea wire:model="content" label="Konten" rows="6" placeholder="Tulis isi pengumuman..." />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:select wire:model="priority" label="Prioritas">
                    <option value="normal">Normal</option>
                    <option value="important">Important</option>
                </flux:select>
                <div class="flex items-end pb-1">
                    <flux:switch wire:model.live="isActive" label="Aktif" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:input type="date" wire:model="startDate" label="Mulai (opsional)" />
                <flux:input type="date" wire:model="endDate" label="Selesai (opsional)" />
            </div>

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
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan' : 'Buat' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- DELETE MODAL --}}
    <flux:modal name="announcement-delete-modal" class="md:w-110">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600" />
                </div>
                <div class="space-y-1 flex-1">
                    <flux:heading size="lg">Hapus Pengumuman?</flux:heading>
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
    @php
        $viewingIsImage = $this->viewing?->file
            && in_array(Str::lower(pathinfo($this->viewing->file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'], true);
    @endphp
    <flux:modal name="announcement-view-modal"
        class="md:min-w-6xl! min-w-2/3!">
        @if ($this->viewing)
            @if ($viewingIsImage)
                {{-- Tampilan Instagram: mobile vertikal (gambar atas, konten bawah), desktop horizontal --}}
                <div class="flex flex-col md:flex-row md:-m-6 md:max-h-[80vh]">
                    <div class="md:w-2/5 bg-zinc-950 flex items-center justify-center md:rounded-l-xl overflow-hidden">
                        <img src="{{ Storage::url($this->viewing->file) }}" alt="{{ $this->viewing->title }}"
                            class="w-full max-h-72 md:max-h-none md:h-full object-contain" />
                    </div>
                    <div class="md:w-3/5 p-6 space-y-4 overflow-y-auto">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center
                                {{ $this->viewing->priority === 'important' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600' }}">
                                <flux:icon name="{{ $this->viewing->priority === 'important' ? 'fire' : 'megaphone' }}" class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <flux:heading size="lg">{{ $this->viewing->title }}</flux:heading>
                                    @if ($this->viewing->priority === 'important')
                                        <flux:badge color="red" size="sm">Important</flux:badge>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-400 mt-1">
                                    {{ $this->viewing->author?->name ?? 'System' }} • {{ $this->viewing->created_at?->format('d M Y, H:i') }}
                                </p>
                            </div>
                        </div>

                        <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-zinc-700 dark:text-zinc-300">{{ $this->viewing->content }}</div>

                        <a href="{{ Storage::url($this->viewing->file) }}" target="_blank"
                            class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                            <flux:icon name="arrow-top-right-on-square" class="w-4 h-4" /> Buka gambar ukuran penuh
                        </a>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center
                            {{ $this->viewing->priority === 'important' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600' }}">
                            <flux:icon name="{{ $this->viewing->priority === 'important' ? 'fire' : 'megaphone' }}" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">{{ $this->viewing->title }}</flux:heading>
                                @if ($this->viewing->priority === 'important')
                                    <flux:badge color="red" size="sm">Important</flux:badge>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-400 mt-1">
                                {{ $this->viewing->author?->name ?? 'System' }} • {{ $this->viewing->created_at?->format('d M Y, H:i') }}
                            </p>
                        </div>
                    </div>

                    <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-zinc-700 dark:text-zinc-300">{{ $this->viewing->content }}</div>

                    @if ($this->viewing->file)
                        <a href="{{ Storage::url($this->viewing->file) }}" target="_blank"
                            class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                            <flux:icon name="paper-clip" class="w-4 h-4" /> Buka lampiran
                        </a>
                    @endif
                </div>
            @endif
        @endif
    </flux:modal>
</div>

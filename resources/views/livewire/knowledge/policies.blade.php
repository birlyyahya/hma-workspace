<?php

use App\Models\SupportPolicy;
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

    public ?int $editingId = null;
    public string $title = '';
    public string $summary = '';
    public string $content = '';
    public bool $isActive = true;
    public $file = null;
    public ?string $existingFile = null;

    public ?int $deletingId = null;
    public ?string $deletingTitle = null;

    #[Computed]
    public function items()
    {
        return SupportPolicy::query()
            ->with('author:id,name,username')
            ->when($this->search, fn ($q) => $q->where(function ($qq) {
                $like = '%'.Str::lower($this->search).'%';
                $qq->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(summary) LIKE ?', [$like]);
            }))
            ->latest()
            ->get();
    }

    public function canManage(): bool
    {
        return Gate::allows('create', SupportPolicy::class);
    }

    public function openCreate(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }
        $this->resetForm();
        Flux::modal('policy-form-modal')->show();
    }

    public function openEdit(int $id): void
    {
        $item = SupportPolicy::find($id);
        if (! $item || Gate::denies('update', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->summary = $item->summary ?? '';
        $this->content = $item->content;
        $this->isActive = (bool) $item->is_active;
        $this->existingFile = $item->file;
        $this->file = null;

        Flux::modal('policy-form-modal')->show();
    }

    public function save(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'isActive' => ['boolean'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $payload = [
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'content' => $data['content'],
            'is_active' => $data['isActive'],
            'created_by' => Auth::id(),
        ];

        if ($this->file) {
            if ($this->existingFile) {
                Storage::delete($this->existingFile);
            }
            $payload['file'] = $this->file->storePublicly('knowledge/policies', config('filesystems.default'));
        }

        if ($this->editingId) {
            $item = SupportPolicy::find($this->editingId);
            if (! $item || Gate::denies('update', $item)) {
                Toaster::error('Anda tidak memiliki akses');
                return;
            }
            $item->update($payload);
            Toaster::success('Policy berhasil diperbarui');
        } else {
            SupportPolicy::create($payload);
            Toaster::success('Policy berhasil dibuat');
        }

        $this->resetForm();
        Flux::modal('policy-form-modal')->close();
    }

    public function confirmDelete(int $id): void
    {
        $item = SupportPolicy::find($id);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        $this->deletingId = $id;
        $this->deletingTitle = $item->title;
        Flux::modal('policy-delete-modal')->show();
    }

    public function delete(): void
    {
        $item = SupportPolicy::find($this->deletingId);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        if ($item->file) {
            Storage::delete($item->file);
        }

        $item->delete();
        Toaster::success('Policy dihapus');

        $this->reset('deletingId', 'deletingTitle');
        Flux::modal('policy-delete-modal')->close();
    }

    public function removeExistingFile(): void
    {
        if (! $this->editingId || ! $this->existingFile) {
            return;
        }
        $item = SupportPolicy::find($this->editingId);
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
        $this->reset(['editingId', 'title', 'summary', 'content', 'file', 'existingFile']);
        $this->isActive = true;
        $this->resetErrorBag();
    }
}; ?>

<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-2">{{ __('Support Center') }}</flux:heading>
        <flux:subheading>{{ __('Aturan dan kebijakan perusahaan') }}</flux:subheading>
        <flux:separator variant="subtle" class="mt-4" />
    </div>

    <x-settings.knowledge-layout
        :heading="__('Policies & Rules')"
        :subheading="__('Akses dan tinjau kebijakan resmi yang berlaku untuk seluruh karyawan.')"
    >
        <x-slot name="action">
            @if ($this->canManage())
                <flux:button icon="plus" variant="primary" wire:click="openCreate">Tambah Policy</flux:button>
            @endif
        </x-slot>

        <div class="mb-5">
            <flux:input
                icon="magnifying-glass"
                placeholder="Cari policy..."
                wire:model.live.debounce.300ms="search"
                clearable
            />
        </div>

        @if ($this->items->count() === 0)
            <div class="flex flex-col items-center justify-center py-16 px-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                <div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center mb-3">
                    <flux:icon name="shield-check" class="w-6 h-6 text-amber-500" />
                </div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Belum ada policy</p>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->items as $item)
                    <details wire:key="pol-{{ $item->id }}" id="{{ $item->slug }}" class="group p-5">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-3">
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="shrink-0 w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                                    <flux:icon name="shield-check" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div class="space-y-1 flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <flux:heading size="base" class="font-semibold text-zinc-800 dark:text-zinc-100">
                                            {{ $item->title }}
                                        </flux:heading>
                                        @if (! $item->is_active)
                                            <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                        @endif
                                    </div>
                                    @if ($item->summary)
                                        <flux:description>{{ $item->summary }}</flux:description>
                                    @endif
                                    <p class="text-xs text-zinc-400">
                                        Updated {{ $item->updated_at?->format('d M Y') }} • {{ $item->author?->name ?? 'System' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
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
                                <span class="transition group-open:rotate-180">
                                    <flux:icon name="chevron-down" variant="solid" class="w-4 h-4 text-zinc-500" />
                                </span>
                            </div>
                        </summary>

                        <div class="mt-4 ml-12 space-y-3">
                            <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-zinc-700 dark:text-zinc-300">{{ $item->content }}</div>

                            @if ($item->file)
                                <a href="{{ Storage::url($item->file) }}" target="_blank"
                                    class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                    <flux:icon name="paper-clip" class="w-4 h-4" /> Buka lampiran
                                </a>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </x-settings.knowledge-layout>

    {{-- FORM MODAL --}}
    <flux:modal name="policy-form-modal" class="md:w-[640px]" wire:close="resetForm">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-amber-500 to-orange-500 flex items-center justify-center">
                    <flux:icon name="shield-check" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingId ? 'Edit Policy' : 'Tambah Policy' }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">Aturan & kebijakan untuk seluruh karyawan</flux:text>
                </div>
            </div>

            <flux:input wire:model="title" label="Judul" placeholder="Code of Conduct, WFH Policy, ..." />
            <flux:textarea wire:model="summary" label="Ringkasan (opsional)" rows="2" />
            <flux:textarea wire:model="content" label="Isi Policy" rows="10" placeholder="Tulis aturan dan ketentuan lengkap di sini..." />

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
    <flux:modal name="policy-delete-modal" class="md:w-110">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600" />
                </div>
                <div class="space-y-1 flex-1">
                    <flux:heading size="lg">Hapus Policy?</flux:heading>
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
</div>

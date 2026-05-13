<?php

use App\Models\Permission;
use App\Services\PermissionCache;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithPagination;

    #[Url(as: 'pq', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $moduleFilter = '';

    public int $perPage = 15;

    public ?int $editingId = null;
    public string $module = '';
    public string $action = '';
    public string $label = '';
    public ?string $description = '';

    public ?int $deleteId = null;
    public string $confirmDelete = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedModuleFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function permissions()
    {
        return Permission::query()
            ->withCount('roles')
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', $term)
                        ->orWhere('label', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($this->moduleFilter !== '', fn ($q) => $q->where('module', $this->moduleFilter))
            ->orderBy('module')
            ->orderBy('action')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function modules(): array
    {
        return Permission::query()
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Permission::query()->count(),
            'modules' => Permission::query()->distinct('module')->count('module'),
        ];
    }

    /** @return string */
    public function getComputedNameProperty(): string
    {
        return Str::lower(trim($this->module)).'.'.Str::lower(trim($this->action));
    }

    /**
     * Daftar action yang valid untuk permission.
     *
     * @return array<string, string>
     */
    public function actionOptions(): array
    {
        return [
            'create' => 'Create',
            'read' => 'Read',
            'update' => 'Update',
            'delete' => 'Delete',
            'comment' => 'Comment',
            'view.all' => 'View All',
            'view.department' => 'View Department',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        Flux::modal('permission-form-modal')->show();
    }

    public function openEdit(int $id): void
    {
        $perm = Permission::find($id);

        if (! $perm) {
            Toaster::error('Permission tidak ditemukan.');

            return;
        }

        $this->editingId = $perm->id;
        $this->module = $perm->module;
        $this->action = $perm->action;
        $this->label = $perm->label;
        $this->description = $perm->description;
        $this->resetErrorBag();

        Flux::modal('permission-form-modal')->show();
    }

    public function save(): void
    {
        // Module: namespace tunggal, hanya huruf/angka/hyphen.
        $this->module = Str::lower(Str::slug($this->module, '-'));

        $name = $this->module.'.'.$this->action;

        $this->validate([
            'module' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'action' => ['required', Rule::in(array_keys($this->actionOptions()))],
            'label' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'module.regex' => 'Module hanya boleh huruf kecil, angka, dan tanda hubung.',
            'action.in' => 'Action harus dipilih dari opsi yang tersedia.',
        ]);

        $nameRule = Rule::unique('permissions', 'name')->ignore($this->editingId);
        validator(['name' => $name], ['name' => [$nameRule]], [
            'name.unique' => 'Kombinasi module + action sudah ada (name: '.$name.').',
        ])->validate();

        try {
            $payload = [
                'name' => $name,
                'module' => $this->module,
                'action' => $this->action,
                'label' => $this->label,
                'description' => $this->description ?: null,
            ];

            if ($this->editingId) {
                Permission::where('id', $this->editingId)->update($payload);
                $perm = Permission::find($this->editingId);
                if ($perm) {
                    PermissionCache::flushForPermission($perm);
                }
                Toaster::success('Permission berhasil diperbarui.');
            } else {
                Permission::create($payload);
                Toaster::success('Permission berhasil dibuat.');
            }

            $this->resetForm();
            Flux::modal('permission-form-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal menyimpan permission: '.$e->getMessage());
        }
    }

    public function openDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = '';
        $this->resetErrorBag();
        Flux::modal('permission-delete-modal')->show();
    }

    public function deletePermission(): void
    {
        $this->validate(['confirmDelete' => ['required']]);

        if (Str::upper($this->confirmDelete) !== 'YA') {
            $this->addError('confirmDelete', 'Ketik "YA" untuk konfirmasi.');

            return;
        }

        $perm = Permission::withCount('roles')->find($this->deleteId);

        if (! $perm) {
            Toaster::error('Permission tidak ditemukan.');

            return;
        }

        if ($perm->roles_count > 0) {
            Toaster::error("Permission masih digunakan {$perm->roles_count} role. Lepaskan dulu dari role tersebut.");

            return;
        }

        try {
            PermissionCache::flushForPermission($perm);
            $perm->delete();
            Toaster::success('Permission berhasil dihapus.');
            $this->reset(['deleteId', 'confirmDelete']);
            Flux::modal('permission-delete-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal menghapus permission: '.$e->getMessage());
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'moduleFilter']);
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'module', 'action', 'label', 'description']);
        $this->resetErrorBag();
    }
}; ?>

<div class="space-y-5">
    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Total Permission</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $this->stats['total'] }}</p>
                </div>
                <div class="rounded-lg bg-emerald-100 p-2.5">
                    <flux:icon.key class="size-5 text-emerald-600" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Module</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ $this->stats['modules'] }}</p>
                </div>
                <div class="rounded-lg bg-zinc-100 p-2.5">
                    <flux:icon.squares-2x2 class="size-5 text-zinc-600" />
                </div>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <flux:input icon="magnifying-glass" wire:model.live.debounce.400ms="search" placeholder="Cari permission..." class="w-full sm:max-w-sm" />

            <div class="flex flex-wrap items-center gap-2">
                <flux:select wire:model.live="moduleFilter" class="w-full sm:w-44">
                    <option value="">Semua module</option>
                    @foreach ($this->modules as $m)
                        <option value="{{ $m }}">{{ strtoupper($m) }}</option>
                    @endforeach
                </flux:select>

                @if ($search || $moduleFilter)
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters" class="cursor-pointer">
                        Reset
                    </flux:button>
                @endif

                <flux:button icon="plus" variant="primary" class="cursor-pointer" wire:click="openCreate">
                    Tambah Permission
                </flux:button>
            </div>
        </div>

        {{-- Datatable --}}
        <div class="relative">
            <div wire:loading.flex wire:target.except="editingId, deleteId" class="absolute inset-0 z-20 flex items-center justify-center bg-white/60 backdrop-blur-sm">
                <div class="flex flex-col items-center gap-2">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-zinc-900 border-t-transparent"></div>
                    <span class="text-sm text-zinc-600">Loading data...</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-225 md:min-w-full text-sm text-left text-zinc-600">
                    <thead class="bg-zinc-50/80 text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-6 py-3 whitespace-nowrap">#</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Module</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Label</th>
                            <th class="px-4 py-3">Dipakai Role</th>
                            <th class="px-6 py-3 text-end whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody wire:loading.class="pointer-events-none" class="divide-y divide-zinc-100">
                        @forelse ($this->permissions as $p)
                            <tr wire:key="perm-{{ $p->id }}" class="transition hover:bg-zinc-50/70">
                                <td class="px-6 py-3 whitespace-nowrap text-zinc-500">
                                    {{ ($this->permissions->currentPage() - 1) * $this->permissions->perPage() + $loop->iteration }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $p->name }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge color="zinc" size="sm">{{ strtoupper($p->module) }}</flux:badge>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-600">{{ $p->action }}</td>
                                <td class="px-4 py-3 text-zinc-800">{{ $p->label }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        <flux:icon.shield-check class="size-3" />
                                        {{ $p->roles_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:tooltip content="Edit">
                                            <flux:button variant="ghost" icon="pencil-square" size="sm" class="cursor-pointer" wire:click="openEdit({{ $p->id }})" />
                                        </flux:tooltip>
                                        <flux:tooltip content="Hapus">
                                            <flux:button :disabled="$p->roles_count > 0" variant="ghost" icon="trash" size="sm" class="cursor-pointer text-red-500 hover:text-red-600" wire:click="openDelete({{ $p->id }})" />
                                        </flux:tooltip>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center gap-2 text-zinc-400">
                                        <flux:icon.key class="size-10" />
                                        <p class="text-sm">Belum ada permission.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-100 p-4 overflow-x-auto md:overflow-visible">
                <div class="min-w-max">
                    {{ $this->permissions->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Form modal --}}
    <flux:modal name="permission-form-modal" class="min-w-xs md:min-w-2xl">
        <form wire:submit.prevent="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Permission' : 'Tambah Permission' }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    Format <code class="rounded bg-zinc-100 px-1 font-mono text-xs">module.action</code> — contoh:
                    <code class="rounded bg-zinc-100 px-1 font-mono text-xs">dar.view.all</code>,
                    <code class="rounded bg-zinc-100 px-1 font-mono text-xs">project.update</code>.
                </flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Module</flux:label>
                    <flux:input wire:model.live.debounce.300ms="module" placeholder="contoh: dar" />
                    <flux:description>Slug huruf kecil, kelompok fitur (tanpa titik).</flux:description>
                    <flux:error name="module" />
                </flux:field>
                <flux:field>
                    <flux:label>Action</flux:label>
                    <flux:select wire:model.live="action" placeholder="Pilih action...">
                        @foreach ($this->actionOptions() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>Pilih aksi yang akan diberi izin pada modul ini.</flux:description>
                    <flux:error name="action" />
                </flux:field>
            </div>

            @php
                $previewModule = Str::lower(Str::slug($module ?: 'module', '-'));
                $previewAction = $action ?: 'action';
            @endphp
            <div class="rounded-lg border border-zinc-100 bg-zinc-50/70 px-3 py-2">
                <p class="text-xs text-zinc-500">Name akan jadi:</p>
                <p class="font-mono text-sm text-zinc-900">
                    {{ ($module || $action) ? $previewModule.'.'.$previewAction : 'module.action' }}
                </p>
            </div>

            <flux:field>
                <flux:label>Label</flux:label>
                <flux:input wire:model="label" placeholder="contoh: View Project" />
                <flux:description>Teks ramah untuk tampilan UI.</flux:description>
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Penjelasan singkat dari permission ini." />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
                <flux:button type="button" variant="outline" x-on:click="$flux.modal('permission-form-modal').close()">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Buat Permission' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete modal --}}
    <flux:modal name="permission-delete-modal" class="min-w-xs md:min-w-md">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-red-50 p-2.5">
                    <flux:icon.trash class="size-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Permission</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        Tindakan ini tidak dapat dibatalkan. Permission yang sedang dipakai role tidak dapat dihapus.
                        Ketik <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-red-600">YA</code> untuk konfirmasi.
                    </flux:text>
                </div>
            </div>
            <flux:input placeholder="Ketik YA untuk konfirmasi..." wire:model="confirmDelete" />
            @error('confirmDelete')
                <flux:text class="text-xs text-red-500">{{ $message }}</flux:text>
            @enderror
            <div class="flex justify-end gap-2">
                <flux:button variant="outline" x-on:click="$flux.modal('permission-delete-modal').close()">Batal</flux:button>
                <flux:button variant="danger" wire:click="deletePermission" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="deletePermission">Hapus</span>
                    <span wire:loading wire:target="deletePermission">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

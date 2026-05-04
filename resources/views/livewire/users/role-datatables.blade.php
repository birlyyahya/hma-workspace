<?php

use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithPagination;

    #[Url(as: 'rq', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $scopeFilter = '';

    #[Url(except: 'desc')]
    public string $sort = 'desc';

    public int $perPage = 10;

    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public ?string $description = '';
    public int $level = 1;
    public ?string $scope = '';
    public bool $canApprove = false;

    public ?int $deleteRoleId = null;
    public string $confirmDelete = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedScopeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedName(string $value): void
    {
        if (!$this->editingId) {
            $this->slug = Str::slug($value);
        }
    }

    #[Computed]
    public function roles()
    {
        return Role::query()
            ->withCount('users')
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('scope', 'like', $term);
                });
            })
            ->when($this->scopeFilter !== '', fn ($q) => $q->where('scope', $this->scopeFilter))
            ->orderBy('level', $this->sort)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function scopes(): array
    {
        return Role::query()
            ->whereNotNull('scope')
            ->distinct()
            ->orderBy('scope')
            ->pluck('scope')
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Role::query()->count(),
            'approver' => Role::query()->where('can_approve', true)->count(),
            'users' => User::query()->count(),
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        Flux::modal('role-form-modal')->show();
    }

    public function openEdit(int $id): void
    {
        $role = Role::find($id);

        if (! $role) {
            Toaster::error('Role tidak ditemukan.');

            return;
        }

        if (! $this->canManage($role->level)) {
            Toaster::error('Anda tidak memiliki izin untuk mengubah role ini.');

            return;
        }

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->slug = $role->slug;
        $this->description = $role->description;
        $this->level = $role->level;
        $this->scope = $role->scope;
        $this->canApprove = (bool) $role->can_approve;
        $this->resetErrorBag();

        Flux::modal('role-form-modal')->show();
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'level' => ['required', 'integer', 'min:1', 'max:100'],
            'scope' => ['nullable', 'string', 'max:50'],
            'canApprove' => ['boolean'],
        ];

        $this->validate($rules, [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
        ]);

        if (! $this->canManage($this->level)) {
            Toaster::error('Anda tidak dapat membuat/mengubah role dengan level lebih tinggi dari role Anda.');

            return;
        }

        try {
            $payload = [
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description ?: null,
                'level' => $this->level,
                'scope' => $this->scope ?: null,
                'can_approve' => $this->canApprove,
            ];

            if ($this->editingId) {
                Role::where('id', $this->editingId)->update($payload);
                Toaster::success('Role berhasil diperbarui.');
            } else {
                Role::create($payload);
                Toaster::success('Role berhasil dibuat.');
            }

            $this->resetForm();
            Flux::modal('role-form-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal menyimpan role: ' . $e->getMessage());
        }
    }

    public function openDelete(int $id): void
    {
        $this->deleteRoleId = $id;
        $this->confirmDelete = '';
        $this->resetErrorBag();
        Flux::modal('role-delete-modal')->show();
    }

    public function deleteRole(): void
    {
        $this->validate(['confirmDelete' => ['required']]);

        if (Str::upper($this->confirmDelete) !== 'YA') {
            $this->addError('confirmDelete', 'Ketik "YA" untuk konfirmasi.');

            return;
        }

        $role = Role::withCount('users')->find($this->deleteRoleId);

        if (! $role) {
            Toaster::error('Role tidak ditemukan.');

            return;
        }

        if (! $this->canManage($role->level)) {
            Toaster::error('Anda tidak memiliki izin untuk menghapus role ini.');

            return;
        }

        if ($role->users_count > 0) {
            Toaster::error("Role masih digunakan oleh {$role->users_count} user. Pindahkan user terlebih dahulu.");

            return;
        }

        try {
            $role->delete();
            Toaster::success('Role berhasil dihapus.');
            $this->reset(['deleteRoleId', 'confirmDelete']);
            Flux::modal('role-delete-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal menghapus role: ' . $e->getMessage());
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'scopeFilter', 'sort']);
        $this->sort = 'desc';
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description', 'level', 'scope', 'canApprove']);
        $this->level = 1;
        $this->resetErrorBag();
    }

    protected function canManage(int $targetLevel): bool
    {
        return (Auth::user()?->role?->level ?? 0) >= $targetLevel;
    }

}; ?>

@php
    $scopeColors = [
        'global' => 'blue',
        'it' => 'indigo',
        'it-software' => 'cyan',
        'it-infra' => 'cyan',
        'hrd' => 'emerald',
        'map' => 'emerald',
    ];
@endphp

<div class="space-y-5">
    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Total Role</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ $this->stats['total'] }}</p>
                </div>
                <div class="rounded-lg bg-zinc-100 p-2.5">
                    <flux:icon.shield-check class="size-5 text-zinc-600" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-indigo-700">Approver</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-900">{{ $this->stats['approver'] }}</p>
                </div>
                <div class="rounded-lg bg-indigo-100 p-2.5">
                    <flux:icon.check-circle class="size-5 text-indigo-600" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Total User</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ $this->stats['users'] }}</p>
                </div>
                <div class="rounded-lg bg-zinc-100 p-2.5">
                    <flux:icon.users class="size-5 text-zinc-600" />
                </div>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <flux:input
                icon="magnifying-glass"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari nama, slug, atau scope role..."
                class="w-full sm:max-w-sm"
            />

            <div class="flex flex-wrap items-center gap-2">
                <flux:select wire:model.live="scopeFilter" class="w-full sm:w-44">
                    <option value="">Semua scope</option>
                    @foreach ($this->scopes as $sc)
                        <option value="{{ $sc }}">{{ $sc }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="sort" class="w-full sm:w-40">
                    <option value="desc">Level tertinggi</option>
                    <option value="asc">Level terendah</option>
                </flux:select>

                @if ($search || $scopeFilter || $sort !== 'desc')
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters" class="cursor-pointer">
                        Reset
                    </flux:button>
                @endif

                <flux:button icon="plus" variant="primary" class="cursor-pointer" wire:click="openCreate">
                    Tambah Role
                </flux:button>
            </div>
        </div>

        {{-- Datatable --}}
        <div class="relative">
            <div
                wire:loading.flex
                wire:target.except="editingId, deleteRoleId"
                class="absolute inset-0 z-20 flex items-center justify-center bg-white/60 backdrop-blur-sm"
            >
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
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Scope</th>
                            <th class="px-4 py-3">Level</th>
                            <th class="px-4 py-3">Approver</th>
                            <th class="px-4 py-3">Users</th>
                            <th class="px-6 py-3 text-end whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody wire:loading.class="pointer-events-none" class="divide-y divide-zinc-100">
                        @forelse ($this->roles as $r)
                            <tr wire:key="role-{{ $r->id }}" class="transition hover:bg-zinc-50/70">
                                <td class="px-6 py-3 whitespace-nowrap text-zinc-500">
                                    {{ ($this->roles->currentPage() - 1) * $this->roles->perPage() + $loop->iteration }}
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-zinc-900">{{ $r->name }}</p>
                                    <p class="text-xs text-zinc-500">{{ $r->slug }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($r->scope)
                                        <flux:badge :color="$scopeColors[$r->scope] ?? 'gray'" size="sm">
                                            {{ $r->scope }}
                                        </flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-semibold text-zinc-900">{{ $r->level }}</span>
                                        <div class="hidden h-1.5 w-16 overflow-hidden rounded-full bg-zinc-100 sm:block">
                                            <div class="h-full bg-zinc-900" style="width: {{ min(100, $r->level) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($r->can_approve)
                                        <flux:badge color="emerald" size="sm" icon="check">Yes</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">No</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">
                                        <flux:icon.user class="size-3" />
                                        {{ $r->users_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:tooltip content="Edit">
                                            <flux:button
                                                :disabled="! $this->canManage($r->level)"
                                                variant="ghost"
                                                icon="pencil-square"
                                                size="sm"
                                                class="cursor-pointer"
                                                wire:click="openEdit({{ $r->id }})"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip content="Hapus">
                                            <flux:button
                                                :disabled="! $this->canManage($r->level) || $r->users_count > 0"
                                                variant="ghost"
                                                icon="trash"
                                                size="sm"
                                                class="cursor-pointer text-red-500 hover:text-red-600"
                                                wire:click="openDelete({{ $r->id }})"
                                            />
                                        </flux:tooltip>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center gap-2 text-zinc-400">
                                        <flux:icon.shield-check class="size-10" />
                                        <p class="text-sm">Tidak ada role yang cocok dengan filter.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-100 p-4 overflow-x-auto md:overflow-visible">
                <div class="min-w-max">
                    {{ $this->roles->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Form modal --}}
    <flux:modal name="role-form-modal" class="min-w-xs md:min-w-3xl">
        <form wire:submit.prevent="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Role' : 'Tambah Role' }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    Role menentukan hak akses dan level hierarki pengguna.
                </flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nama Role</flux:label>
                    <flux:input wire:model.live.debounce.300ms="name" placeholder="Contoh: Manager" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <div class="flex gap-2">
                        <flux:label>Slug</flux:label>
                        <flux:tooltip content="Otomatis dari nama. Hanya huruf kecil, angka, dan tanda hubung.">
                            <flux:icon.exclamation-circle class="size-5" />
                        </flux:tooltip>
                    </div>
                    <flux:input wire:model.live="slug" placeholder="manager" />
                    <flux:error name="slug" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Singkat saja, tujuan / lingkup role." />
                <flux:error name="description" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <div class="flex gap-2">
                        <flux:label>Level (1-100)</flux:label>
                        <flux:tooltip content="Semakin tinggi, semakin besar wewenang.">
                            <flux:icon.exclamation-circle class="size-5" />
                        </flux:tooltip>
                    </div>

                    <flux:input type="number" min="1" max="100" wire:model="level" />
                    <flux:error name="level" />
                </flux:field>
                <flux:field>
                    <flux:label>Scope</flux:label>
                    <flux:input wire:model="scope" placeholder="contoh: it-software" list="role-scope-suggestions" />
                    <datalist id="role-scope-suggestions">
                        @foreach ($this->scopes as $sc)
                            <option value="{{ $sc }}"></option>
                        @endforeach
                    </datalist>
                    <flux:error name="scope" />
                </flux:field>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50/60 px-4 py-3">
                <div>
                    <flux:label>Dapat menyetujui (approver)</flux:label>
                    <flux:description>Aktifkan jika role ini dapat melakukan approval pengajuan.</flux:description>
                </div>
                <flux:switch wire:model="canApprove" />
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
                <flux:button type="button" variant="outline" x-on:click="$flux.modal('role-form-modal').close()">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Buat Role' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete modal --}}
    <flux:modal name="role-delete-modal" class="min-w-xs md:min-w-md">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-red-50 p-2.5">
                    <flux:icon.trash class="size-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Role</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        Tindakan ini tidak dapat dibatalkan. Role yang sedang dipakai user tidak dapat dihapus.
                        Ketik <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-red-600">YA</code> untuk konfirmasi.
                    </flux:text>
                </div>
            </div>
            <flux:input placeholder="Ketik YA untuk konfirmasi..." wire:model="confirmDelete" />
            @error('confirmDelete')
                <flux:text class="text-xs text-red-500">{{ $message }}</flux:text>
            @enderror
            <div class="flex justify-end gap-2">
                <flux:button variant="outline" x-on:click="$flux.modal('role-delete-modal').close()">Batal</flux:button>
                <flux:button variant="danger" wire:click="deleteRole" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="deleteRole">Hapus</span>
                    <span wire:loading wire:target="deleteRole">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<?php

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCache;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster;
use App\Jobs\DeleteUserToApiIzinJob;
use App\Jobs\DeleteUserToApiPM;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'asc')]
    public string $sort = 'asc';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    public int $perPage = 10;

    public ?int $resetUserId = null;

    public ?int $deleteUserId = null;
    public string $confirmDelete = '';

    public ?int $editUserId = null;
    public string $editName = '';
    public string $editEmail = '';
    public ?int $editRole = null;
    public ?string $emailVerified = null;
    public string $password = '';
    public string $password_confirmation = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('level', 'desc')->get(['id', 'name', 'level']);
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('role:id,name,level')
            ->when($this->search, function ($query) {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('username', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->status === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($this->status === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($this->role !== '', fn ($q) => $q->where('role_id', $this->role))
            ->orderBy('role_id', $this->sort)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        $total = User::query()->count();
        $verified = User::query()->whereNotNull('email_verified_at')->count();

        return [
            'total' => $total,
            'verified' => $verified,
            'unverified' => $total - $verified,
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'role', 'status', 'sort']);
        $this->sort = 'asc';
        $this->resetPage();
    }

    public function resetPassword(): void
    {
        $user = User::find($this->resetUserId);

        if (! $user) {
            Toaster::error('User tidak ditemukan.');

            return;
        }

        if (! $this->canManage($user)) {
            Toaster::error('Anda tidak memiliki izin untuk mereset password user ini.');

            return;
        }

        try {
            $user->update(['password' => Hash::make('password')]);
            Toaster::success('Password berhasil direset menjadi "password".');
            $this->resetUserId = null;
            Flux::modal('confirm-reset-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal reset password: ' . $e->getMessage());
        }
    }

    public function deleteUser(): void
    {
        $this->validate([
            'confirmDelete' => ['required'],
        ]);

        if (Str::upper($this->confirmDelete) !== 'YA') {
            $this->addError('confirmDelete', 'Ketik "YA" untuk konfirmasi.');

            return;
        }

        $user = User::find($this->deleteUserId);

        if (! $user) {
            Toaster::error('User tidak ditemukan.');

            return;
        }

        if ($user->id === Auth::id()) {
            Toaster::error('Anda tidak dapat menghapus akun sendiri.');

            return;
        }

        if (! $this->canManage($user)) {
            Toaster::error('Anda tidak memiliki izin untuk menghapus user ini.');

            return;
        }

        try {
            DeleteUserToApiPM::dispatch($user->id);
            DeleteUserToApiIzinJob::dispatch($user->username);

            $user->delete();

            Toaster::success('User berhasil dihapus.');
            $this->reset(['confirmDelete', 'deleteUserId']);
            Flux::modal('delete-user-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal menghapus user: ' . $e->getMessage());
        }
    }

    public function editUser(User $user): void
    {
        if (! $this->canManage($user)) {
            Toaster::error('Anda tidak memiliki izin untuk mengubah user ini.');

            return;
        }

        $this->editUserId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editRole = $user->role_id;
        $this->emailVerified = $user->email_verified_at?->toIso8601String();
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetErrorBag();

        Flux::modal('edit-user-modal')->show();
    }

    public function updateUser(): void
    {
        $user = User::find($this->editUserId);

        if (! $user) {
            Toaster::error('User tidak ditemukan.');

            return;
        }

        if (! $this->canManage($user)) {
            Toaster::error('Anda tidak memiliki izin untuk mengubah user ini.');

            return;
        }

        $this->validate([
            'editName' => ['required', 'string', 'min:3', 'max:100'],
            'editEmail' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'editRole' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'min:6', 'confirmed'],
        ]);

        try {
            $data = [
                'name' => $this->editName,
                'email' => $this->editEmail,
                'role_id' => $this->editRole,
            ];

            if (! empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            }

            $user->update($data);
            PermissionCache::flushForUser($user->id);
            Toaster::success('User berhasil diperbarui.');
            $this->reset(['editUserId', 'editName', 'editEmail', 'editRole', 'password', 'password_confirmation']);
            Flux::modal('edit-user-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal memperbarui user: ' . $e->getMessage());
        }
    }

    public function resendVerificationEmail(int $userId): void
    {
        $user = User::find($userId);

        if (! $user) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
            Toaster::success('Email verifikasi berhasil dikirim.');
        } catch (\Throwable $e) {
            Toaster::error('Gagal mengirim email verifikasi: ' . $e->getMessage());
        }
    }

    protected function canManage(User $target): bool
    {
        $authLevel = Auth::user()?->role?->level ?? 0;
        $targetLevel = $target->role?->level ?? 0;

        return $authLevel >= $targetLevel;
    }

}; ?>

@php
    $roleColors = [
        'super-admin' => 'blue',
        'gm' => 'indigo',
        'manager' => 'amber',
        'asmen' => 'amber',
        'spv-it-infra' => 'yellow',
        'spv-it-software' => 'yellow',
        'staff-it-software' => 'cyan',
        'staff-it-infra' => 'cyan',
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
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Total User</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ $this->stats['total'] }}</p>
                </div>
                <div class="rounded-lg bg-zinc-100 p-2.5">
                    <flux:icon.users class="size-5 text-zinc-600" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Verified</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $this->stats['verified'] }}</p>
                </div>
                <div class="rounded-lg bg-emerald-100 p-2.5">
                    <flux:icon.check-badge class="size-5 text-emerald-600" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Unverified</p>
                    <p class="mt-1 text-2xl font-bold text-amber-900">{{ $this->stats['unverified'] }}</p>
                </div>
                <div class="rounded-lg bg-amber-100 p-2.5">
                    <flux:icon.exclamation-triangle class="size-5 text-amber-600" />
                </div>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <flux:input
                    icon="magnifying-glass"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama, username, atau email..."
                    class="w-full sm:max-w-sm"
                />
                <div wire:loading.delay wire:target="search" class="text-xs text-zinc-500 animate-pulse">
                    mencari...
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:select wire:model.live="status" class="w-full sm:w-40">
                    <option value="">Semua status</option>
                    <option value="verified">Verified</option>
                    <option value="unverified">Unverified</option>
                </flux:select>

                <flux:select wire:model.live="role" class="w-full sm:w-44">
                    <option value="">Semua role</option>
                    @foreach ($this->roles as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="sort" class="w-full sm:w-36">
                    <option value="asc">Role ASC</option>
                    <option value="desc">Role DESC</option>
                </flux:select>

                @if ($search || $role || $status || $sort !== 'asc')
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="x-mark"
                        wire:click="clearFilters"
                        class="cursor-pointer"
                    >
                        Reset
                    </flux:button>
                @endif

                @can('user.create')
                <flux:modal.trigger name="create-user-modal">
                    <flux:button icon="plus" variant="primary" class="cursor-pointer">
                        Tambah user
                    </flux:button>
                </flux:modal.trigger>
                @endcan
            </div>
        </div>

        {{-- Datatable --}}
        <div class="relative">
            <div
                wire:loading.flex
                wire:target.except="resetUserId, deleteUserId, editUser"
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
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-6 py-3 text-end whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody wire:loading.class="pointer-events-none" class="divide-y divide-zinc-100">
                        @forelse ($this->users as $u)
                            <tr wire:key="user-{{ $u->id }}" class="transition hover:bg-zinc-50/70">
                                <td class="px-6 py-3 whitespace-nowrap text-zinc-500">
                                    {{ ($this->users->currentPage() - 1) * $this->users->perPage() + $loop->iteration }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-full bg-linear-to-br from-zinc-700 to-zinc-900 text-xs font-semibold text-white">
                                            {{ $u->initials() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-zinc-900">{{ $u->name }}</p>
                                            <p class="truncate text-xs text-zinc-500">&#64;{{ $u->username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="break-all md:break-normal text-zinc-700">{{ $u->email }}</span>
                                        @if ($u->email_verified_at)
                                            <flux:tooltip content="Verified">
                                                <flux:icon.check-badge class="size-5 text-emerald-500" variant="solid" />
                                            </flux:tooltip>
                                        @else
                                            <flux:badge color="amber" size="sm">Unverified</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge
                                        :color="$roleColors[$u->role->slug ?? ''] ?? 'gray'"
                                        size="sm"
                                    >
                                        {{ $u->role?->name ?? '—' }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-3 text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:tooltip content="Edit">
                                            <flux:button
                                                :disabled="! $this->canManage($u)"
                                                variant="ghost"
                                                icon="pencil-square"
                                                size="sm"
                                                class="cursor-pointer"
                                                wire:click="editUser({{ $u->id }})"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip content="Reset password">
                                            <flux:button
                                                :disabled="! $this->canManage($u)"
                                                variant="ghost"
                                                icon="key"
                                                size="sm"
                                                class="cursor-pointer"
                                                wire:click="$set('resetUserId', {{ $u->id }})"
                                                x-on:click="$flux.modal('confirm-reset-modal').show()"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip content="Hapus">
                                            <flux:button
                                                :disabled="! $this->canManage($u) || $u->id === auth()->id()"
                                                variant="ghost"
                                                icon="trash"
                                                size="sm"
                                                class="cursor-pointer text-red-500 hover:text-red-600"
                                                wire:click="$set('deleteUserId', {{ $u->id }})"
                                                x-on:click="$flux.modal('delete-user-modal').show()"
                                            />
                                        </flux:tooltip>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center gap-2 text-zinc-400">
                                        <flux:icon.users class="size-10" />
                                        <p class="text-sm">Tidak ada user yang cocok dengan filter.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-100 p-4 overflow-x-auto md:overflow-visible">
                <div class="min-w-max">
                    {{ $this->users->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Reset password confirm modal --}}
    <flux:modal name="confirm-reset-modal" class="min-w-xs md:min-w-md">
        <div class="space-y-6">
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-amber-50 p-2.5">
                    <flux:icon.key class="size-5 text-amber-600" />
                </div>
                <div>
                    <flux:heading size="lg">Reset Password</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        Password akan direset menjadi <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-800">password</code>. User dapat menggantinya setelah login.
                    </flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="outline" x-on:click="$flux.modal('confirm-reset-modal').close()">Batal</flux:button>
                <flux:button variant="primary" wire:click="resetPassword" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="resetPassword">Reset password</span>
                    <span wire:loading wire:target="resetPassword">Memproses...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete confirm modal --}}
    <flux:modal name="delete-user-modal" class="min-w-xs md:min-w-md">
        <div class="space-y-6">
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-red-50 p-2.5">
                    <flux:icon.trash class="size-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus User</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        Tindakan ini tidak dapat dibatalkan. Ketik
                        <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-red-600">YA</code>
                        untuk konfirmasi.
                    </flux:text>
                </div>
            </div>
            <flux:input placeholder="Ketik YA untuk konfirmasi..." wire:model="confirmDelete" />
            <div class="flex justify-end gap-2">
                <flux:button variant="outline" x-on:click="$flux.modal('delete-user-modal').close()">Batal</flux:button>
                <flux:button variant="danger" wire:click="deleteUser" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="deleteUser">Hapus</span>
                    <span wire:loading wire:target="deleteUser">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit user modal --}}
    <flux:modal name="edit-user-modal" class="min-w-xs md:min-w-md">
        <form wire:submit.prevent="updateUser" class="space-y-5">
            <div>
                <flux:heading size="lg">Edit User</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">Perbarui informasi pengguna.</flux:text>
            </div>

            <flux:field>
                <flux:label>Nama</flux:label>
                <flux:input icon="user" wire:model="editName" />
                <flux:error name="editName" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <div class="flex gap-2">
                    <flux:input icon="envelope" wire:model="editEmail" type="email" class="flex-1" />
                    @if ($emailVerified === null && $editUserId)
                        <flux:button type="button" variant="filled" wire:click="resendVerificationEmail({{ $editUserId }})">
                            Resend
                        </flux:button>
                    @endif
                </div>
                <flux:error name="editEmail" />
            </flux:field>

            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="editRole">
                    <option value="">Pilih role</option>
                    @foreach ($this->roles as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="editRole" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Password baru</flux:label>
                    <flux:input icon="key" wire:model="password" placeholder="Kosongkan jika tidak diubah" type="password" viewable />
                    <flux:error name="password" />
                </flux:field>
                <flux:field>
                    <flux:label>Konfirmasi password</flux:label>
                    <flux:input icon="key" wire:model="password_confirmation" placeholder="Ulangi password" type="password" viewable />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
                <flux:button type="button" variant="outline" x-on:click="$flux.modal('edit-user-modal').close()">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updateUser">Update</span>
                    <span wire:loading wire:target="updateUser">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:users.user-create-modal @user-created="$refresh" />
</div>

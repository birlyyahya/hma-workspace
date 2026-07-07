<?php

use App\Jobs\RegisterUserToApiIzinJob;
use App\Jobs\RegisterUserToApiProjectJob;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public ?int $role = null;

    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('level', 'desc')->get(['id', 'name', 'level']);
    }

    public function createUser(): void
    {
        if (!Auth::user()->can('create.user')) {
            Toaster::error('Anda tidak memiliki izin untuk membuat user baru.');
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'exists:roles,id'],
        ]);

        try {
            $plainPassword = $this->password;

            $user = User::create([
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'password' => Hash::make($plainPassword),
                'role_id' => $this->role,
            ]);

            RegisterUserToApiProjectJob::dispatch($user, $plainPassword);
            RegisterUserToApiIzinJob::dispatch($user, $plainPassword);

            $this->dispatch('user-created');
            Toaster::success('User berhasil dibuat.');
            $this->reset(['name', 'username', 'email', 'password', 'password_confirmation', 'role']);
            $this->resetErrorBag();
            Flux::modal('create-user-modal')->close();
        } catch (\Throwable $e) {
            Toaster::error('Gagal membuat user: ' . $e->getMessage());
        }
    }

}; ?>

<div>
    <flux:modal name="create-user-modal" class="min-w-xs md:min-w-md">
        <form wire:submit.prevent="createUser" class="space-y-5">
            <div>
                <flux:heading size="lg">Buat User Baru</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">Tambahkan akun pengguna baru beserta role-nya.</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nama Lengkap</flux:label>
                    <flux:input icon="user" placeholder="John Doe" wire:model="name" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:input icon="at-symbol" placeholder="johndoe" wire:model="username" />
                    <flux:error name="username" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input icon="envelope" type="email" placeholder="user@example.com" wire:model="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="role">
                    <option value="">Pilih role</option>
                    @foreach ($this->roles as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Password</flux:label>
                    <flux:input icon="key" type="password" placeholder="Minimal 6 karakter" wire:model="password" viewable />
                    <flux:error name="password" />
                </flux:field>
                <flux:field>
                    <flux:label>Konfirmasi Password</flux:label>
                    <flux:input icon="key" type="password" placeholder="Ulangi password" wire:model="password_confirmation" viewable />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
                <flux:button type="button" variant="outline" x-on:click="$flux.modal('create-user-modal').close()">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createUser">Buat User</span>
                    <span wire:loading wire:target="createUser">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

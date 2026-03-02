<?php

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithPagination;

    public $page;
    public $perPage = 10;
    public $search = '';
    public $sort = 'asc';
    public $role = '';
    public $status = '';

    // reset password
    public $resetUserId;

    // delete user
    public $deleteUserId;
    public $confirmDelete = '';

    // edit user
    public $editUserId;
    public $editName;
    public $editEmail;
    public $editRole;
    public $emailVerified;
    public $password;
    public $password_confirmation;


     #[Computed]
    public function user() {
       return User::query()
        ->when($this->search, function ($query) {
            $query->where('username', 'like', '%'.$this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%');
        })
         ->when($this->status === 'verified', function ($query) {
        $query->whereNotNull('email_verified_at');
        })
        ->when($this->status === 'unverified', function ($query) {
            $query->whereNull('email_verified_at');
        })
        ->when($this->role, function ($query) {
            $query->where('role_id', $this->role);
        })
        ->orderBy('role_id', $this->sort)
        ->paginate($this->perPage);
    }

    public function resetPassword() {
        try {
             $user = User::find($this->resetUserId);

             $user->update([
                 'password' => bcrypt('123')
             ]);

            if(Auth::user()->role->level < $user->role->level) {
            Toaster::error('You do not have permission to reset this user');
            return;
            }

             Toaster::success('Password has been reset');
             Flux::modal('confirm-reset-modal')->close();
         }catch(\Exception $e) {
             Toaster::error('Failed to reset password: ' . $e->getMessage());
         }
    }

    public function deleteUser() {
        $this->validate([
            'confirmDelete' => ['required' ],
            ]);
            if (strtoupper($this->confirmDelete) !== 'YA') {
                $this->addError('confirmDelete', 'Type YA to confirm');
                Toaster::error('Yout must type "YA" to confirm');
                return;
                }
        try {
            $user = User::find($this->deleteUserId);

            if(Auth::user()->role->level < $user->role->level) {
            Toaster::error('You do not have permission to delete this user');
            return;
            }
            $user->delete();
            Toaster::success('User has been deleted');
            $this->reset('confirmDelete');
            Flux::modal('delete-user-modal')->close();
        }catch (\Exception $e) {
            Toaster::error('Delete failed: ' . $e->getMessage());
        }
    }

    public function getUserEdit($id) {
        return $this->user->map($this->user->where('id', $id)->first(), function ($user) {
            $this->editUserId = $user->id;
            $this->editName = $user->name;
            $this->editEmail = $user->email;
            $this->editRole = $user->role_id;
            $this->edita = $user->status;
        });
    }

    public function editUser(User $user) {
        $this->editUserId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editRole = $user->role_id;
        $this->emailVerified = $user->email_verified_at;
        $this->password = '';

        Flux::modal('edit-user-modal')->show();
    }

    public function updateUser(User $user) {
        $this->validate([
            'editName' => ['required', 'min:3'],
            'editEmail' => ['required', 'email'],
            'editRole' => ['required'],
            'password' => ['nullable','confirmed'],
        ]);

        if(Auth::user()->role->level < $user->role->level) {
            Toaster::error('You do not have permission to update this user');
            return;
        }

         try {
            $data = [
                'name' => $this->editName,
                'email' => $this->editEmail,
                'role_id' => $this->editRole,
            ];

            if (!empty($this->password)) {
                $data['password'] = bcrypt($this->password);
            }

            $user->update($data);
            Toaster::success('User has been updated');
            $this->reset('editUserId', 'editName', 'editEmail', 'editRole', 'password');
            Flux::modal('edit-user-modal')->close();
            }catch (\Exception $e) {
                $this->reset('editUserId', 'editName', 'editEmail', 'editRole', 'password');
                Toaster::error('Update failed: ' . $e->getMessage());
        }
    }

    public function resendVerificationEmail(User $user) {
        try {
            $user->sendEmailVerificationNotification();
            Toaster::success('Verification email has been sent');
        }catch (\Exception $e) {
            Toaster::error('Failed to send verification email: ' . $e->getMessage());
        }
    }

}; ?>

<div class="space-y-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-col items-start gap-1 md:flex-row md:items-end md:gap-4">
            <flex:heading class="font-bold text-xl">User Management</flex:heading>
            <flux:description class="text-sm text-gray-500">Kelola penggunan</flux:description>
        </div>
        <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto md:gap-4">
            <flux:modal.trigger name="create-user-modal">
                <flux:button icon="plus-circle" href="" variant="outline" class="cursor-pointer w-full sm:w-auto">
                    Create user
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>
    <div class="flex flex-col bg-white p-4 rounded-lg shadow-sm space-y-2">
        <flux:input icon="magnifying-glass" wire:model.live.debounce.400ms="search" placeholder="Search name, username, or email ..." class="w-full" />
        <div wire:loading.delay wire:target="search" class="text-sm text-blue-500 animate-pulse">
        </div>

    </div>
    <div class="bg-white/80 relative rounded-lg border border-zinc-200 overflow-hidden">
        <div wire:loading.flex wire:target.except="resetUserId, deleteUserId, editUser" class="absolute inset-0 z-20
                flex items-center justify-center
                bg-white/50 backdrop-blur-sm">

            <div class="flex flex-col items-center gap-2">
                <div class="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
                <span class="text-sm text-gray-600">Loading data...</span>
            </div>
        </div>
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center md:w-auto md:gap-3">
                <flux:label class="shrink-0">Sort by</flux:label>
                <flux:select wire:model.live="sort" class="w-full md:w-48">
                    <option value="" disabled>Sort by</option>
                    <option value="asc">ASC</option>
                    <option value="desc">DESC</option>
                </flux:select>
            </div>
            <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 md:w-auto md:grid-cols-2">
                <flux:select wire:model.live="status" class="w-full md:w-48">
                    <option value="" disabled>Status</option>
                    <option value="verified">Verified</option>
                    <option value="unverified">Unverified</option>
                </flux:select>
                <flux:select wire:model.live="role" class="w-full md:w-48">
                    <option value="" disabled>Role</option>
                    <option value="1">Super Admin</option>
                    <option value="2">General Manager</option>
                    <option value="3">Manager</option>
                    <option value="4">Asisten Manager</option>
                    <option value="5">SPV IT Infra</option>
                    <option value="6">SPV IT Software</option>
                    <option value="7">IT Staff</option>
                    <option value="8">HRD</option>
                    <option value="9">MAP</option>
                </flux:select>
            </div>
        </div>

        {{-- Datatable --}}
        <div class="overflow-x-auto">
            <table class="min-w-[900px] md:min-w-full text-sm text-left text-gray-600 ">
                <thead class="bg-white text-xs uppercase shadow-sm text-gray-500 ">
                    <tr>
                        <th class="px-4 py-3 md:px-6 whitespace-nowrap">No</th>
                        <th class="px-3 py-3 md:px-4">Username</th>
                        <th class="px-3 py-3 md:px-4">Email</th>
                        <th class="px-3 py-3 md:px-4">Role</th>
                        <th class="px-4 py-3 md:px-6 text-end whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="pointer-events-none">
                    @forelse ($this->user as $data)
                    <tr wire:key="{{ $data['id'] }}" class="border-b border-gray-100 hover:bg-gray-50 transition">
                        <td class="px-4 py-3 md:px-6 whitespace-nowrap">{{ ($this->user->currentPage() - 1) * $this->user->perPage() + $loop->iteration }}</td>
                        <td class="px-3 py-3 md:px-4 whitespace-nowrap">{{ $data['username'] }}</td>
                        <td class="px-3 py-3 md:px-4">
                            <div class="flex items-center gap-2">
                                <span class="break-all md:break-normal">{{ $data['email'] }}</span>
                                @if(!$data['email_verified_at'])
                                <flux:badge :color="$data['email_verified_at'] ? 'green' : 'red'" size="sm">
                                    {{ $data['email_verified_at'] ? '' : 'Unverified' }}
                                </flux:badge>
                                @else
                                <flux:icon.check-badge class="text-green-500 size-5" variant="solid" />
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-3 md:px-4 ">
                            <flux:badge :color="
                                match($data['role']['name']) {
                                    'Super Admin' => 'blue',
                                    'General Manager' => 'blue',
                                    'Manager' => 'yellow',
                                    'Asisten Manager' => 'yellow',
                                    'SPV IT Infra' => 'yellow',
                                    'SPV IT Software' => 'yellow',
                                    'IT Staff' => 'yellow',
                                    'HRD' => 'emerald',
                                    'MAP' => 'emerald',
                                    default => 'gray',
                                }
                            " size="sm">
                                {{ $data['role']['name'] }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 md:px-6 text-end">
                            <div class="md:flex items-center justify-end">
                                <flux:tooltip content="Edit">
                                    <flux:button :disabled="Auth::user()->role->level < $data['role']['level']" wire:key="{{ $data['id'] }}" variant="ghost" icon="pencil-square" class="cursor-pointer" wire:click="editUser({{ $data['id'] }})" x-on:click="$flux.modal('form-user-modal').show()" />
                                </flux:tooltip>
                                <flux:tooltip content="Reset Password">
                                    <flux:button :disabled="Auth::user()->role->level < $data['role']['level']" wire:key="{{ $data['id'] }}" variant="ghost" color="gray" icon="key" class="cursor-pointer" wire:click="$set('resetUserId', {{ $data['id'] }})" x-on:click="$flux.modal('confirm-reset-modal').show()" />
                                </flux:tooltip>
                                <flux:tooltip content="Delete">
                                    <flux:button :disabled="Auth::user()->role->level < $data['role']['level']" wire:key="{{ $data['id'] }}" variant="ghost" iconVariant="outline" icon="trash" class="cursor-pointer" wire:click="$set('deleteUserId', {{ $data['id'] }})" x-on:click="$flux.modal('delete-user-modal').show()" />
                                </flux:tooltip>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-3 py-3 md:px-6 text-center text-gray-400">
                            Tidak ada data user
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 overflow-x-auto md:overflow-visible">
            <div class="min-w-max">
                {{ $this->user->links(data: ['scrollTo' => false]) }}
            </div>
        </div>
    </div>

    <flux:modal name="confirm-reset-modal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reset Password</flux:heading>
                <flux:text class="mt-2">Apakah anda yakin ingin mereset password user ini?</flux:text>
            </div>
            <div class="flex justify-between">
                <div>
                    <flux:button variant="outline" x-on:click="$flux.modal('confirmResetPassword').close()">Cancel</flux:button>
                </div>
                <div>
                    <flux:button variant="primary" wire:click="resetPassword">Reset</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="delete-user-modal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete User</flux:heading>
                <flux:text class="mt-2">Apakah anda yakin ingin menghapus user ini?</flux:text>
                <flux:text class="mt-2">ketik <code class="text-red-500">YA</code> untuk konfirmasi</flux:text>
            </div>
            <div class="flex flex-col gap-2">
                <flux:input placeholder="Ketik YA ..." wire:model="confirmDelete"></flux:input>
            </div>
            <div class="flex justify-between">
                <div>
                    <flux:button variant="outline" x-on:click="$flux.modal('deleteUser').close()">Cancel</flux:button>
                </div>
                <div>
                    <flux:button variant="danger" wire:click="deleteUser">Delete</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-user-modal" class="min-w-xs md:min-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit User</flux:heading>
            </div>
            <div class="w-full">
                <flux:input icon="user" wire:model="editName" class="w-full" type="text"></flux:input>
            </div>
            <div class="w-full flex gap-2">
                <flux:input icon="envelope" wire:model="editEmail" class="w-full rounded-lg" type="email"></flux:input>
                @if($this->emailVerified == null)
                <flux:button variant="filled" wire:click="resendVerificationEmail({{ $this->editUserId }})">Resend</flux:button>
                @endif
            </div>
            <div class="w-full">
                <flux:input icon="key" wire:model="password" placeholder="New password ..." class="w-full" type="password" viewable></flux:input>
            </div>
            <div class="w-full">
                <flux:input icon="key" wire:model="password_confirmation" placeholder="Confirm new password ..." class="w-full" type="password" viewable></flux:input>
            </div>
            <div class="w-full">
                <flux:select wire:model.live="editRole" class="w-full">
                    <option value="" disabled>Role</option>
                    <option value="1">Super Admin</option>
                    <option value="2">General Manager</option>
                    <option value="3">Manager</option>
                    <option value="4">Asisten Manager</option>
                    <option value="5">SPV IT Infra</option>
                    <option value="6">SPV IT Software</option>
                    <option value="7">IT Staff</option>
                    <option value="8">HRD</option>
                    <option value="9">MAP</option>
                </flux:select>
            </div>
            <div class="flex justify-between">
                <div>
                    <flux:button variant="outline" x-on:click="$flux.modal('editUser').close()">Cancel</flux:button>
                </div>
                <div>
                    <flux:button variant="primary" wire:click="updateUser({{ $editUserId }})">Update</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <livewire:users.user-create-modal @user-created="$refresh"/>

</div>

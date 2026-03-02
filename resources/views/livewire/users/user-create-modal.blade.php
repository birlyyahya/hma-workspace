<?php

use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $name = '';
    public $username = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = '';


    public function createUser(){
        $this->validate([
            'name' => ['required', 'min:3'],
            'username' => ['required', 'min:3'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        $data = [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'role_id' => $this->role
        ];
        try {
            User::create($data);

            $this->dispatch('user-created');

            Toaster::success('User has been created');
            $this->reset('name', 'username', 'email', 'password', 'password_confirmation');
            Flux::modal('create-user-modal')->close();
            }catch(\Exception $e) {
                Toaster::error('Failed to create user: ' . $e->getMessage());
        }
    }

}; ?>

<div>
        <flux:modal name="create-user-modal" class="min-w-xs md:min-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Create User</flux:heading>
            </div>
            <div class="w-full">
                <flux:input icon="user" placeholder="Name" wire:model="name" class="w-full" type="text"></flux:input>
            </div>
            <div class="w-full">
                <flux:input icon="user" placeholder="Username" wire:model="username" class="w-full" type="text"></flux:input>
            </div>
            <div class="w-full">
                <flux:input icon="envelope" wire:model="email" placeholder="email" class="w-full" type="email"></flux:input>
            </div>
            <div class="w-full">
                <flux:input icon="key" wire:model="password" placeholder="New password ..." class="w-full" type="password" viewable></flux:input>
            </div>
            <div class="w-full">
                <flux:input icon="key" wire:model="password_confirmation" placeholder="Confirm new password ..." class="w-full" type="password" viewable></flux:input>
            </div>
            <div class="w-full">
                <flux:select wire:model="role" class="w-full">
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
                    <flux:button variant="outline" x-on:click="$flux.modal('create-user-modal').close()">Cancel</flux:button>
                </div>
                <div>
                    <flux:button variant="primary" wire:click="createUser">Create</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>

<?php

use Flux\Flux;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public $email;
    public $password;
    public $verificationSent = false;


    public function mount()
    {
        $this->email = Auth::user()->email;
    }

    public function updateEmail()
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'current_password'],
        ]);

        auth()->user()->update(['email' => $this->email]);

        Auth::user()->sendEmailVerificationNotification();

        Toaster::success('Email updated successfully!');

        Flux::modal('update-email-modal')->close();

        $this->verificationSent = true;

    }

}; ?>

<div>
    @if ($verificationSent)
    <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
        {{ __('A new verification link has been sent to the email address you provided during registration.') }}
    </flux:text>
    @else
    <flux:text class="text-center">if you forgetten your email, you can update it here and click resend verification email</flux:text>
    @endif
    <div class="grid grid-cols-3 gap-3 mt-3">
        <flux:input icon="envelope" wire:model="email" class="w-full col-span-2" type="email" value="{{ Auth::user()->email }}"></flux:input>
        <flux:modal.trigger name="update-email-modal">
            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Confirm') }}
            </flux:button>
        </flux:modal.trigger>
        <flux:modal name="update-email-modal">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Update Email</flux:heading>
                    <flux:text class="mt-2">Input your password to update your email</flux:text>
                </div>
                <div class="w-full">
                    <flux:input icon="key" wire:model="password" class="w-full" type="password" viewable></flux:input>
                </div>
                <div class="flex justify-between">
                    <flux:button variant="outline" x-on:click="$flux.modal('update-email-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="updateEmail">Update</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>

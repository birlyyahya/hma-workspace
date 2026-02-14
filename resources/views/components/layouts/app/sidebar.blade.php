<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50">
    <flux:sidebar class="border-e border-zinc-200 bg-white transition-[width] duration-300 ease-in-out overflow-hidden" x-bind:class="$store.ui.openSidebar ? 'w-64' : 'w-18.5'" @mouseenter="!$store.ui.pinned && ($store.ui.openSidebar = true)" @mouseleave="!$store.ui.pinned && ($store.ui.openSidebar = false)">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
            <flux:tooltip :content="__('Pin sidebar')" position="right">
            <button type="button" x-on:click="$store.ui.togglePin()" class="cursor-pointer">
                <svg class="text-zinc-500" :class="{'rotate-180': $store.ui.pinned}" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7.5 3.75V16.25M3.4375 16.25H16.5625C17.08 16.25 17.5 15.83 17.5 15.3125V4.6875C17.5 4.17 17.08 3.75 16.5625 3.75H3.4375C2.92 3.75 2.5 4.17 2.5 4.6875V15.3125C2.5 15.83 2.92 16.25 3.4375 16.25Z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
            </flux:tooltip>
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    <span class="inline-block ml-3" x-show="$store.ui.openSidebar" x-cloak x-transition.opacity.duration.300ms>
                        Dashboard
                    </span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="archive-box" :href="route('projects')" :current="request()->routeIs('projects')" wire:navigate>
                    <span class="inline-block ml-3" x-show="$store.ui.openSidebar" x-cloak x-transition.opacity.duration.300ms>
                        Projects
                    </span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="qr-code" :href="route('events')" :current="request()->routeIs('events') || request()->routeIs('events.show')" wire:navigate>
                    <span class="inline-block ml-3" x-show="$store.ui.openSidebar" x-cloak x-transition.opacity.duration.300ms>
                        Events
                    </span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="banknotes" :href="route('chartered-accountants')" :current="request()->routeIs('chartered-accountants')" wire:navigate>
                    <span class="inline-block ml-3" x-show="$store.ui.openSidebar" x-cloak x-transition.opacity.duration.300ms>
                        Chartered Accountants
                    </span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="envelope" :href="route('chartered-accountants')" :current="request()->routeIs('chartered-accountants')" wire:navigate>
                    <span class="inline-block ml-3" x-show="$store.ui.openSidebar" x-cloak x-transition.opacity.duration.300ms>
                        Izin
                    </span>
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />
        <flux:sidebar.nav>
            <flux:sidebar.group class="grid">
                <flux:sidebar.item icon="book-open" :href="route('chartered-accountants')" :current="request()->routeIs('chartered-accountants')" wire:navigate>
                    <span class="inline-block ml-3" x-show="$store.ui.openSidebar" x-cloak x-transition.opacity.duration.300ms>
                        Documentation
                    </span>
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>
     <x-toaster-hub />
     <audio id="notifSound" src="{{ asset('sounds/notification.mp3') }}" preload="auto"></audio>


    {{ $slot }}

    @fluxScripts
</body>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('ui', {
            openSidebar: JSON.parse(localStorage.getItem('sidebarOpen')) ?? false
            , pinned: JSON.parse(localStorage.getItem('sidebarPinned')) ?? false,

            togglePin() {
                this.pinned = !this.pinned
                this.openSidebar = this.pinned
                localStorage.setItem('sidebarPinned', this.pinned)
                localStorage.setItem('sidebarOpen', this.openSidebar)
            }
        , })
    })
    Livewire.on('play-notification-sound', () => {
        document.getElementById('notifSound').play();
    });
</script>
</html>

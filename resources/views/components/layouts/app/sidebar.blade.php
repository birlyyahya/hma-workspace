<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50">
    <flux:sidebar sticky collapsible class="bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                Dashboard

            </flux:sidebar.item>
            <flux:sidebar.group icon="document-text" expandable heading="Projects" class="grid">
                <livewire:components.sidebar-item />
            </flux:sidebar.group>
            <flux:sidebar.item icon="qr-code" :href="route('events')" :current="request()->routeIs('events') || request()->routeIs('events.show')" wire:navigate>
                Events
            </flux:sidebar.item>
            <flux:sidebar.item icon="banknotes" :href="route('chartered-accountants')" :current="request()->routeIs('chartered-accountants')" wire:navigate>
                Chartered Accountants
            </flux:sidebar.item>
              <flux:sidebar.item icon="bookmark-square" :href="route('izin')" :current="request()->routeIs('izin') || request()->routeIs('izin.show') || request()->routeIs('izin.laporan-pengajuan')" wire:navigate>
                Daily Activity Report (DAR)
            </flux:sidebar.item>
            <flux:sidebar.item icon="archive-box" :href="route('inventaris')" :current="request()->routeIs('inventaris')" wire:navigate>
                Inventaris
            </flux:sidebar.item>
            <flux:sidebar.item icon="envelope" :href="route('izin')" :current="request()->routeIs('izin') || request()->routeIs('izin.show') || request()->routeIs('izin.laporan-pengajuan')" wire:navigate>
                Izin
            </flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:spacer />
        <flux:sidebar.nav>
            <flux:sidebar.group class="grid">
                @if(Auth::user()->role->level > 50)
                <flux:sidebar.item icon="users" :href="route('users')" :current="request()->routeIs('users')" wire:navigate>
                    User Management
                </flux:sidebar.item>
                @endif
                <flux:sidebar.item icon="book-open" :href="route('knowledge.articles')" :current="request()->routeIs('knowledge.articles')" wire:navigate>
                    Knowledge Hub
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <x-desktop-user-menu class="hidden lg:block" class="font-light" :name="auth()->user()->name" />
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
            openSidebar: JSON.parse(localStorage.getItem('sidebarOpen')) ? ? false
            , pinned: JSON.parse(localStorage.getItem('sidebarPinned')) ? ? false,

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

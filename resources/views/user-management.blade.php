<x-layouts.app :title="__('User Management - HMA Workspace')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('User Management') }}
        </h2>
    </x-slot>

   @if(Auth::user()->hasPermission('user.view.all') )
   <div class="max-h-screen overflow-auto px-3 py-5 md:px-6 md:py-6">
       <div
           x-data="{ tab: $persist('users').as('user-management-tab') }"
           class="mx-auto max-w-7xl space-y-5"
       >
           <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
               <div class="space-y-1">
                   <flux:heading size="xl" class="font-bold tracking-tight">User & Role Management</flux:heading>
                   <flux:description class="text-sm text-zinc-500">
                       Kelola pengguna sistem dan struktur peran beserta level aksesnya.
                   </flux:description>
               </div>

               <div class="inline-flex w-full items-center gap-1 rounded-xl border border-zinc-200 bg-white p-1 shadow-sm md:w-auto">
                   <button
                       type="button"
                       @click="tab = 'users'"
                       :class="tab === 'users' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100'"
                       class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition md:flex-none"
                   >
                       <flux:icon.users class="size-4" />
                       Users
                   </button>
                   <button
                       type="button"
                       @click="tab = 'roles'"
                       :class="tab === 'roles' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100'"
                       class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition md:flex-none"
                   >
                       <flux:icon.shield-check class="size-4" />
                       Roles
                   </button>
                   <button
                       type="button"
                       @click="tab = 'permissions'"
                       :class="tab === 'permissions' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100'"
                       class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition md:flex-none"
                   >
                       <flux:icon.key class="size-4" />
                       Permissions
                   </button>
               </div>
           </div>

           <div x-show="tab === 'users'" x-cloak>
               <livewire:users.user-datatables lazy />
           </div>

           <div x-show="tab === 'roles'" x-cloak>
               <livewire:users.role-datatables lazy />
           </div>

           <div x-show="tab === 'permissions'" x-cloak>
               <livewire:users.permission-datatables lazy />
           </div>
       </div>
   </div>
   @else
        {{ abort(403) }}
   @endif
</x-layouts.app>

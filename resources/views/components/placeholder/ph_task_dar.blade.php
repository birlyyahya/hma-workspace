<div>

    <header class="px-5 py-4">
               <div class="flex items-center gap-3">
                   <flux:modal.trigger name="create-task">
                       <flux:button icon="plus-circle" iconClasses="size-6" variant="outline">
                           Tambah task
                       </flux:button>
                   </flux:modal.trigger>

                   <div class="flex flex-1 items-center gap-4">
                       <div class="h-px flex-1 bg-slate-200/70"></div>
                       <h2 class="text-lg font-semibold tracking-tight text-slate-800">Tasks</h2>
                       <div class="h-px flex-1 bg-slate-200/70"></div>
                   </div>

                   <flux:input wire:model="search" icon="magnifying-glass" placeholder="Search task..." class="w-full md:w-64" />
               </div>
           </header>
   <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
       @forelse([1,2,3] as $item)
       <div class="rounded-2xl bg-white ring-1 ring-slate-200/70 shadow-sm animate-pulse">
           <div class="p-5">
               <div class="flex items-start justify-between gap-3">

                   {{-- Title + description --}}
                   <div class="flex-1 space-y-2">
                       <div class="h-4 w-3/4 rounded bg-slate-200"></div>
                       <div class="h-3 w-full rounded bg-slate-200"></div>
                       <div class="h-3 w-5/6 rounded bg-slate-200"></div>
                   </div>

                   {{-- Menu button --}}
                   <div class="h-9 w-9 rounded-full bg-slate-200"></div>
               </div>

               {{-- Badges --}}
               <div class="mt-4 flex gap-2">
                   <div class="h-5 w-16 rounded-full bg-slate-200"></div>
                   <div class="h-5 w-20 rounded-full bg-slate-200"></div>
               </div>
           </div>

           {{-- Footer --}}
           <div class="flex items-center justify-between gap-3 border-t border-slate-200/70 px-5 py-4">

               {{-- Avatars --}}
               <div class="flex -space-x-2">
                   <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                   <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                   <div class="h-7 w-7 rounded-full bg-slate-200"></div>
               </div>

               {{-- Date --}}
               <div class="h-3 w-24 rounded bg-slate-200"></div>
           </div>
       </div>
       @empty
       @endforelse
   </div>
</div>

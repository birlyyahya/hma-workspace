<div>
    <div class="min-h-screen bg-linear-to-b from-zinc-50 to-white px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <flux:skeleton.group animate="shimmer">
                {{-- Top bar --}}
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <flux:skeleton class="h-8 w-20 rounded-full"></flux:skeleton>
                        <flux:skeleton class="h-4 w-28"></flux:skeleton>
                    </div>
                    <flux:skeleton class="h-9 w-9 rounded-full"></flux:skeleton>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {{-- LEFT: Task content --}}
                    <div class="lg:col-span-2 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-zinc-200/70 sm:p-8 space-y-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:skeleton class="h-6 w-20 rounded-full"></flux:skeleton>
                            <flux:skeleton class="h-6 w-24 rounded-full"></flux:skeleton>
                        </div>

                        <flux:skeleton class="h-9 w-3/4"></flux:skeleton>

                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <flux:skeleton class="h-6 w-6 rounded-full"></flux:skeleton>
                                <flux:skeleton class="h-4 w-28"></flux:skeleton>
                            </div>
                            <flux:skeleton class="h-4 w-40"></flux:skeleton>
                            <flux:skeleton class="h-4 w-24"></flux:skeleton>
                        </div>

                        <div class="border-t border-zinc-100 pt-6 space-y-3">
                            <flux:skeleton class="h-4 w-28"></flux:skeleton>
                            <flux:skeleton class="h-4 w-full"></flux:skeleton>
                            <flux:skeleton class="h-4 w-11/12"></flux:skeleton>
                            <flux:skeleton class="h-4 w-2/3"></flux:skeleton>
                        </div>

                        <div class="border-t border-zinc-100 pt-6 space-y-3">
                            <flux:skeleton class="h-4 w-20"></flux:skeleton>
                            <div class="flex flex-wrap gap-2">
                                @for ($i = 0; $i < 3; $i++)
                                    <flux:skeleton class="h-7 w-28 rounded-full"></flux:skeleton>
                                @endfor
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: Sidebar --}}
                    <div class="space-y-5">
                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200/70 space-y-4">
                            <flux:skeleton class="h-4 w-24"></flux:skeleton>
                            @for ($i = 0; $i < 4; $i++)
                                <div class="flex items-center justify-between">
                                    <flux:skeleton class="h-4 w-16"></flux:skeleton>
                                    <flux:skeleton class="h-4 w-24"></flux:skeleton>
                                </div>
                            @endfor
                        </div>

                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200/70 space-y-4">
                            <div class="flex items-center justify-between">
                                <flux:skeleton class="h-4 w-28"></flux:skeleton>
                                <flux:skeleton class="h-4 w-6"></flux:skeleton>
                            </div>
                            @for ($i = 0; $i < 2; $i++)
                                <div class="flex items-center gap-3">
                                    <flux:skeleton class="h-10 w-10 rounded-lg"></flux:skeleton>
                                    <div class="space-y-2">
                                        <flux:skeleton class="h-4 w-32"></flux:skeleton>
                                        <flux:skeleton class="h-3 w-20"></flux:skeleton>
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                {{-- Activity / comments section --}}
                <div class="mt-5 rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200/70">
                    <div class="flex items-center gap-2 border-b border-zinc-100 px-5 py-4">
                        <flux:skeleton class="h-4 w-4 rounded"></flux:skeleton>
                        <flux:skeleton class="h-4 w-20"></flux:skeleton>
                        <flux:skeleton class="h-5 w-6 rounded-full"></flux:skeleton>
                    </div>

                    <div class="divide-y divide-zinc-100">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="flex items-start gap-3 px-5 py-4">
                                <flux:skeleton class="h-9 w-9 shrink-0 rounded-full"></flux:skeleton>
                                <div class="flex-1 space-y-2">
                                    <flux:skeleton class="h-4 w-32"></flux:skeleton>
                                    <flux:skeleton class="h-4 w-3/4"></flux:skeleton>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <div class="border-t border-zinc-100 px-5 py-4">
                        <flux:skeleton class="h-20 w-full rounded-2xl"></flux:skeleton>
                    </div>
                </div>
            </flux:skeleton.group>
        </div>
    </div>
</div>

<div>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">
            <flux:skeleton.group animate="shimmer" class="space-y-4">
                <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                    <div class="flex flex-col items-start gap-2 md:flex-row md:items-end md:gap-4">
                        <flux:skeleton class="h-8 w-72"></flux:skeleton>
                        <flux:skeleton class="h-7 w-28 rounded-xl"></flux:skeleton>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex -space-x-2">
                            @for ($i = 0; $i < 4; $i++)
                                <flux:skeleton class="h-8 w-8 rounded-full border-2 border-white"></flux:skeleton>
                            @endfor
                        </div>
                        <flux:skeleton class="h-9 w-9 rounded-full"></flux:skeleton>
                    </div>
                </div>

                <div class="border-b border-zinc-200 pt-4">
                    <div class="flex items-center px-6 gap-8 overflow-x-auto pb-1">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="flex items-center gap-2 pb-3">
                                <flux:skeleton class="h-4 w-4 rounded"></flux:skeleton>
                                <flux:skeleton class="h-4 w-20"></flux:skeleton>
                            </div>
                        @endfor
                    </div>
                </div>
            </flux:skeleton.group>

            <div class="bg-zinc-50 min-h-screen py-6">
                <flux:skeleton.group animate="shimmer" class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 lg:col-span-8">
                        <div class="bg-white rounded-2xl p-8 shadow-sm space-y-8">
                            <div class="flex justify-between items-start">
                                <div class="space-y-3">
                                    <flux:skeleton class="h-4 w-36"></flux:skeleton>
                                    <flux:skeleton class="h-7 w-72"></flux:skeleton>
                                </div>
                                <div class="flex gap-2">
                                    <flux:skeleton class="h-8 w-20 rounded-lg"></flux:skeleton>
                                    <flux:skeleton class="h-8 w-8 rounded-lg"></flux:skeleton>
                                </div>
                            </div>

                            <div class="space-y-5">
                                @for ($i = 0; $i < 4; $i++)
                                    <div class="flex items-center gap-4">
                                        <flux:skeleton class="h-4 w-4 rounded"></flux:skeleton>
                                        <flux:skeleton class="h-4 w-20"></flux:skeleton>
                                        <flux:skeleton class="h-7 w-40 rounded-full"></flux:skeleton>
                                    </div>
                                @endfor
                            </div>

                            <div class="space-y-3">
                                <flux:skeleton class="h-5 w-20"></flux:skeleton>
                                <flux:skeleton class="h-20 w-full rounded-xl"></flux:skeleton>
                            </div>

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <flux:skeleton class="h-5 w-36"></flux:skeleton>
                                </div>
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="flex justify-between items-center rounded-xl">
                                        <div class="flex items-center gap-4">
                                            <flux:skeleton class="w-10 h-10 rounded-lg"></flux:skeleton>
                                            <div class="space-y-2">
                                                <flux:skeleton class="h-4 w-36"></flux:skeleton>
                                                <flux:skeleton class="h-3 w-28"></flux:skeleton>
                                            </div>
                                        </div>
                                        <div class="flex gap-3">
                                            <flux:skeleton class="h-8 w-16 rounded-lg"></flux:skeleton>
                                            <flux:skeleton class="h-8 w-24 rounded-lg"></flux:skeleton>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 lg:col-span-4 space-y-6">
                        @for ($i = 0; $i < 2; $i++)
                            <div class="bg-white rounded-2xl p-6 shadow-sm space-y-5">
                                <div class="flex justify-between items-center">
                                    <flux:skeleton class="h-5 w-28"></flux:skeleton>
                                    <flux:skeleton class="h-6 w-6 rounded"></flux:skeleton>
                                </div>
                                <div class="space-y-4">
                                    @for ($j = 0; $j < 3; $j++)
                                        <div class="flex items-start gap-3">
                                            <flux:skeleton class="w-9 h-9 rounded-full"></flux:skeleton>
                                            <div class="space-y-2">
                                                <flux:skeleton class="h-4 w-24"></flux:skeleton>
                                                <flux:skeleton class="h-3 w-40"></flux:skeleton>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        @endfor
                    </div>
                </flux:skeleton.group>
            </div>
        </div>
    </div>
</div>

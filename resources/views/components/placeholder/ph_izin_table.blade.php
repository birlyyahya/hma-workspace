<div>
    <div class="bg-white relative rounded-2xl border border-zinc-200 overflow-hidden">
        {{-- Toolbar --}}
        <div class="p-4 border-b border-zinc-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="size-5 rounded bg-zinc-200 animate-pulse hidden sm:block"></div>
                <div class="space-y-1.5">
                    <div class="h-3.5 w-24 rounded bg-zinc-200 animate-pulse"></div>
                    <div class="h-3 w-32 rounded bg-zinc-100 animate-pulse"></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 lg:flex lg:items-center lg:gap-2">
                <div class="h-8 w-full lg:w-36 rounded-md bg-zinc-200 animate-pulse"></div>
                <div class="h-8 w-full lg:w-36 rounded-md bg-zinc-200 animate-pulse"></div>
                <div class="h-8 w-full lg:w-40 rounded-md bg-zinc-200 animate-pulse"></div>
                <div class="h-8 w-full lg:w-40 rounded-md bg-zinc-200 animate-pulse"></div>
            </div>
        </div>

        <table class="min-w-full text-sm text-left">
            <thead class="bg-zinc-50/80 border-b border-zinc-200 text-[11px] uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Alasan</th>
                    <th class="px-4 py-3 font-medium">Progress</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @for ($i = 0; $i < 5; $i++)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="h-4 w-24 rounded bg-zinc-200 animate-pulse"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="size-6 rounded-full bg-zinc-200 animate-pulse"></div>
                                <div class="h-4 w-32 rounded bg-zinc-200 animate-pulse"></div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-4 w-48 rounded bg-zinc-200 animate-pulse"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-zinc-100 rounded-full overflow-hidden">
                                    <div class="bg-zinc-300 h-full rounded-full w-1/2 animate-pulse"></div>
                                </div>
                                <div class="h-3 w-9 rounded bg-zinc-200 animate-pulse"></div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-5 w-20 rounded-full bg-zinc-200 animate-pulse"></div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <div class="h-8 w-16 rounded-md bg-zinc-200 animate-pulse"></div>
                                <div class="h-8 w-16 rounded-md bg-zinc-200 animate-pulse"></div>
                            </div>
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <nav class="flex flex-col md:flex-row md:items-center md:justify-between p-4 gap-4 border-t border-zinc-100">
            <div class="h-3 w-44 rounded bg-zinc-200 animate-pulse"></div>
            <ul class="flex items-center -space-x-px text-sm">
                <li>
                    <div class="h-9 w-9 rounded-l-lg bg-zinc-100 border border-zinc-200 animate-pulse"></div>
                </li>
                @for ($i = 0; $i < 5; $i++)
                    <li>
                        <div class="h-9 w-9 bg-zinc-100 border border-zinc-200 animate-pulse"></div>
                    </li>
                @endfor
                <li>
                    <div class="h-9 w-9 rounded-r-lg bg-zinc-100 border border-zinc-200 animate-pulse"></div>
                </li>
            </ul>
        </nav>
    </div>
</div>

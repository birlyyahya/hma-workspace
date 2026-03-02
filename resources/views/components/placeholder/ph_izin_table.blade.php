<div>
    <div class="overflow-x-auto bg-white/80 relative rounded-lg border border-zinc-200">
        <div class="p-4 flex items-center justify-between">
                <div class="h-6 w-24 rounded bg-gray-200 animate-pulse"></div>

                <div class="flex gap-4 items-center">
                   <div class="h-6 w-24 rounded bg-gray-200 animate-pulse"></div>

                    <div class="h-6 w-5 rounded bg-gray-200 animate-pulse"></div>
                    <div class="h-6 w-24 rounded bg-gray-200 animate-pulse"></div>
                </div>
                <div class="h-6 w-24 rounded bg-gray-200 animate-pulse"></div>
            </div>
        <table class="min-w-full text-sm text-left text-gray-600">
            <thead class="bg-white text-xs uppercase shadow-sm text-gray-500">
                <tr>
                    <th class="px-6 py-3">Tanggal</th>
                    <th class="px-6 py-3">Nama</th>
                    <th class="px-6 py-3">Alasan</th>
                    <th class="px-6 py-3">Progress</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody>
                @for ($i = 0; $i < 5; $i++)
                    <tr class="border-b border-gray-100">
                        <td class="px-6 py-3">
                            <div class="h-4 w-24 rounded bg-gray-200 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="h-4 w-32 rounded bg-gray-200 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="h-4 w-48 rounded bg-gray-200 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gray-300 h-2 rounded-full w-1/2 animate-pulse"></div>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="h-6 w-20 rounded-full bg-gray-200 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <div class="h-8 w-16 rounded-md bg-gray-200 animate-pulse"></div>
                                <div class="h-8 w-24 rounded-md bg-gray-200 animate-pulse"></div>
                            </div>
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <nav class="flex flex-col md:flex-row md:items-center md:justify-between p-4 gap-4" aria-label="Table navigation">
            <span class="text-sm text-gray-600">
                <span class="inline-block h-4 w-44 rounded bg-gray-200 animate-pulse"></span>
            </span>

            <ul class="flex items-center -space-x-px text-sm">
                <li>
                    <div class="px-3 h-9 flex items-center justify-center border border-gray-300 bg-white rounded-l-lg">
                        <span class="h-3 w-14 rounded bg-gray-200 animate-pulse"></span>
                    </div>
                </li>
                @for ($i = 0; $i < 5; $i++)
                    <li>
                        <div class="w-9 h-9 flex items-center justify-center border border-gray-300 bg-white">
                            <span class="h-3 w-3 rounded bg-gray-200 animate-pulse"></span>
                        </div>
                    </li>
                @endfor
                <li>
                    <div class="px-3 h-9 flex items-center justify-center border border-gray-300 bg-white rounded-r-lg">
                        <span class="h-3 w-10 rounded bg-gray-200 animate-pulse"></span>
                    </div>
                </li>
            </ul>
        </nav>
    </div>
</div>

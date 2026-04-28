<div>
    <div class="py-8 max-h-screen overflow-auto space-y-6">
        {{-- Hero Header --}}
        <div class="bg-white rounded-2xl shadow-sm border border-zinc-200 overflow-hidden">
            <div class="relative h-40">
                <img src="https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=1600&auto=format"
                    alt="banner" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-linear-to-r from-black/70 via-black/40 to-transparent"></div>
                <div class="absolute inset-0 p-6 flex flex-col justify-end">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge color="green" size="sm">Ongoing</flux:badge>
                        <flux:badge color="zinc" size="sm" class="bg-white/90!">
                            <flux:icon.globe-alt class="w-3 h-3 mr-1" /> Hybrid
                        </flux:badge>
                    </div>
                    <h1 class="text-2xl font-bold text-white">Annual Design Conference 2026</h1>
                    <p class="text-white/80 text-sm">Conference · 4 hari · Jakarta</p>
                </div>
            </div>

            <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100">
                <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                    <span class="flex items-center gap-1.5">
                        <flux:icon.calendar class="w-4 h-4 text-gray-400" />
                        22 - 25 Apr 2026
                    </span>
                    <span class="flex items-center gap-1.5">
                        <flux:icon.clock class="w-4 h-4 text-gray-400" />
                        09:00 - 16:00 WIB
                    </span>
                    <span class="flex items-center gap-1.5">
                        <flux:icon.map-pin class="w-4 h-4 text-gray-400" />
                        Hotel Mulia, Jakarta
                    </span>
                </div>
                <div class="flex gap-2">
                    <flux:button href="{{ route('event.registration', 1) }}" icon="identification" variant="filled"
                        size="sm" wire:navigate>Registration</flux:button>
                    <flux:button href="{{ route('event.scan', 1) }}" icon="qr-code" variant="primary" size="sm"
                        wire:navigate>Scan Check-in</flux:button>
                </div>
            </div>
        </div>

        {{-- Stats + Progress --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $stats = [
                        ['label' => 'Total Peserta', 'value' => 124, 'icon' => 'users', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
                        ['label' => 'Confirmed', 'value' => 110, 'icon' => 'check-badge', 'color' => 'text-green-600', 'bg' => 'bg-green-50'],
                        ['label' => 'Checked-in Today', 'value' => 86, 'icon' => 'finger-print', 'color' => 'text-accent', 'bg' => 'bg-red-50'],
                        ['label' => 'Belum Hadir', 'value' => 24, 'icon' => 'user-minus', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50'],
                    ];
                @endphp
                @foreach ($stats as $stat)
                    <div class="bg-white rounded-2xl p-5 border border-zinc-200 hover:shadow-md transition flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-xl {{ $stat['bg'] }}">
                            <flux:icon :name="$stat['icon']" class="w-6 h-6 {{ $stat['color'] }}" />
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs tracking-wide text-gray-500 font-medium">{{ $stat['label'] }}</span>
                            <p class="text-xl font-bold text-gray-900 leading-tight">{{ $stat['value'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col items-center justify-center gap-3 p-6 bg-white border border-zinc-200 rounded-2xl">
                <h5 class="text-4xl font-bold text-gray-900">2</h5>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Hari Tersisa</p>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-accent h-2 rounded-full transition-all" style="width: 50%"></div>
                </div>
                <div class="flex w-full justify-between text-xs">
                    <span class="text-gray-500">Day 2 of 4</span>
                    <span class="font-medium text-gray-700">50%</span>
                </div>
            </div>
        </div>

        {{-- Day Selector --}}
        <div x-data="{ active: 1 }" class="bg-white rounded-2xl border border-zinc-200 p-2">
            <ul class="flex gap-1 overflow-x-auto">
                @for ($i = 1; $i <= 4; $i++)
                    <li class="flex-1 min-w-28">
                        <button x-on:click="active = {{ $i }}"
                            :class="active === {{ $i }} ? 'bg-accent text-white shadow-sm' : 'text-gray-600 hover:bg-zinc-50'"
                            class="w-full px-4 py-3 rounded-xl transition cursor-pointer text-left">
                            <p class="text-[10px] uppercase tracking-wider opacity-80">Day {{ $i }}</p>
                            <p class="text-sm font-semibold">{{ Carbon\Carbon::parse('2026-04-22')->addDays($i - 1)->format('d M') }}</p>
                        </button>
                    </li>
                @endfor
            </ul>
        </div>

        {{-- Guest List --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-6">
            <div class="flex flex-wrap justify-between items-center mb-5 gap-3">
                <div>
                    <flux:heading size="lg">Daftar Peserta</flux:heading>
                    <flux:description class="text-xs">{{ count($guests) }} peserta terdaftar</flux:description>
                </div>
                <flux:modal.trigger name="form-guest-modal">
                    <flux:button size="sm" variant="primary" icon="plus">Add Peserta</flux:button>
                </flux:modal.trigger>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-5">
                <flux:input type="search" placeholder="Cari nama / NIP..." icon="magnifying-glass"
                    wire:model.live.debounce.350ms="searchQuery" class="lg:col-span-1" />
                <flux:select wire:model.live="searchStatus" placeholder="Semua status">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="registered">Registered</flux:select.option>
                    <flux:select.option value="checked_in">Checked In</flux:select.option>
                    <flux:select.option value="not_checked_in">Not Checked In</flux:select.option>
                    <flux:select.option value="cancelled">Cancelled</flux:select.option>
                </flux:select>
                <div class="flex gap-2">
                    <flux:select wire:model.live="filterSort" placeholder="Sort">
                        <flux:select.option value="name">Nama</flux:select.option>
                        <flux:select.option value="organization">Organisasi</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="filterOrder" placeholder="Order">
                        <flux:select.option value="asc">ASC</flux:select.option>
                        <flux:select.option value="desc">DESC</flux:select.option>
                    </flux:select>
                </div>
            </div>

            @if (!empty($this->guests) && count($this->guests) > 0)
                <div class="overflow-x-auto rounded-xl border border-zinc-200">
                    <table class="min-w-full text-sm text-left text-gray-600">
                        <thead class="bg-zinc-50 text-xs uppercase text-gray-500 tracking-wider">
                            <tr>
                                <th class="px-6 py-3">Nama</th>
                                <th class="px-6 py-3">Jabatan</th>
                                <th class="px-6 py-3">Phone</th>
                                <th class="px-6 py-3">Organisasi</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($this->guests as $guest)
                                <tr wire:key="{{ $guest['id'] }}-{{ $guest['confirm_attendance'] }}"
                                    class="hover:bg-zinc-50 transition">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="https://i.pravatar.cc/100?img={{ $guest['id'] + 10 }}"
                                                class="w-8 h-8 rounded-full" />
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $guest['name'] }}</p>
                                                <p class="text-xs text-gray-500">NIP {{ $guest['nip'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">{{ $guest['jabatan'] }}</td>
                                    <td class="px-6 py-3 text-xs">{{ $guest['phone'] }}</td>
                                    <td class="px-6 py-3 text-xs">{{ $guest['organization'] ?? '-' }}</td>
                                    <td class="px-6 py-3">
                                        <flux:badge :color="match ($guest['status']) {
                                            'registered' => 'blue',
                                            'checked_in' => 'green',
                                            'not_checked_in' => 'amber',
                                            'cancelled' => 'zinc',
                                        }" size="sm">
                                            {{ Str::title(str_replace('_', ' ', $guest['status'])) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            @if (!empty($guest['qr_generated']))
                                                <flux:modal.trigger name="qr-modal-{{ $guest['id'] }}">
                                                    <flux:button variant="primary" size="sm" icon="qr-code">QR</flux:button>
                                                </flux:modal.trigger>
                                            @else
                                                <flux:tooltip content="QR belum di-generate">
                                                    <flux:button disabled size="sm" icon="qr-code">QR</flux:button>
                                                </flux:tooltip>
                                            @endif
                                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                                wire:click="edit({{ $guest['id'] }})" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-16 text-gray-400">
                    <flux:icon.users class="w-12 h-12 mx-auto mb-3 opacity-30" />
                    <p>Belum ada peserta terdaftar.</p>
                </div>
            @endif
        </div>
    </div>
</div>

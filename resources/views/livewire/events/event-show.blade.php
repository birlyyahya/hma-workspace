<div>
    <div class="py-8 max-h-screen overflow-auto space-y-6">
        <div class="bg-white rounded-xl shadow-md justify-between flex items-center p-4 gap-6">
            <div class="space-y-2">
                <flux:heading size="lg" class="text-[1.25rem] font-semibold">{{ $header ?? 'Events' }}</flux:heading>
                <flux:description class="font-light ml-auto text-zinc-500">{{ $description ?? 'Stay updated with upcoming events this '. today()->format('F') }}</flux:description>
            </div>
            <div >
                <flux:button href="{{ route('event.scan', 1) }}" icon="qr-code" variant="primary">Scan Check In</flux:button>
                <flux:button href="{{ route('event.scan', 1) }}" icon="qr-code" variant="primary" color="yellow">Registration</flux:button>
                <flux:button href="" icon="qr-code" variant="outline" class="shadow-sm">Manual Check In</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-5 gap-3">

            <div class="col-span-4 grid grid-rows-[auto_1fr] gap-3">
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 h-fit">
                    <div class="bg-white  rounded-2xl p-5 shadow-sm hover:shadow-md transition flex items-center gap-4">
                        <div class="flex-shrink-0 p-3  rounded-xl">
                            <flux:icon name="users" class="w-8 h-8 text-red-700" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs tracking-wide text-gray-500  font-medium">
                                Total Guests
                            </span>
                            <p class="text-lg font-semibold text-gray-900 leading-tight">
                                50
                            </p>
                        </div>
                    </div>

                    {{-- Date Widget --}}
                    <div class="bg-white  border border-zinc-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition flex items-center gap-4">
                        <div class="flex-shrink-0 p-3  rounded-xl">
                            <flux:icon name="check-badge" class="w-8 h-8 text-red-700" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs tracking-wide text-gray-500  font-medium">
                                Total Confirmed
                            </span>
                            <p class="text-lg font-semibold text-gray-900 leading-tight">
                                50
                            </p>
                        </div>
                    </div>

                    {{-- Time Widget --}}
                    <div class="bg-white  border border-zinc-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition flex items-center gap-4">
                        <div class="flex-shrink-0 p-3  rounded-xl">
                            <flux:icon name="check" class="w-8 h-8 text-red-700" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs tracking-wide text-gray-500  font-medium">
                                Checked In Today
                            </span>
                            <p class="text-lg font-semibold text-gray-900 leading-tight">
                                2
                            </p>
                        </div>
                    </div>

                    <div class="bg-white  border border-zinc-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition flex items-center gap-4">
                        <div class="flex-shrink-0 p-3  rounded-xl">
                            <flux:icon name="users" class="w-8 h-8 text-red-700" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs tracking-wide text-gray-500  font-medium">
                                Not Checked In Today
                            </span>
                            <p class="text-lg font-semibold text-gray-900 leading-tight">
                                10
                            </p>
                        </div>
                    </div>
                </div>

                <div class="block p-6 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md  transition-all">
                    <!-- Event Name -->
                    <h5 class="text-2xl font-bold flex items-center justify-between text-gray-900 ">
                        Annual Design Conference
                        <flux:badge color="green" class="text-xs font-medium ml-2">
                            Ongoing
                        </flux:badge>
                    </h5>


                    <!-- Event Info Bar -->
                    <div class="flex flex-wrap items-center text-sm text-gray-400  mt-1 mb-4 gap-4">
                        <!-- Dates -->
                        <div class="flex items-center gap-1">
                            <flux:icon name="calendar" class="w-4 h-4 text-gray-400" />
                            <span>
                                22 Jan 2023
                                -
                                25 Jan 2023
                            </span>
                        </div>

                        <!-- Times -->
                        <div class="flex items-center gap-1">
                            <flux:icon name="clock" class="w-4 h-4 text-gray-400" />
                            <span>
                                10:00 - 15:00
                            </span>
                        </div>

                        <!-- Location -->
                        <div class="flex items-center gap-1">
                            <flux:icon name="map-pin" class="w-4 h-4 text-gray-400" />
                            <span>Jakarta</span>
                        </div>

                    </div>

                    <!-- Event Description -->
                    <p class="text-sm text-gray-700 ">
                        Lorem ipsum dolor sit, amet consectetur adipisicing elit. Fugiat, porro!
                    </p>
                </div>
            </div>

            <div class="flex flex-col items-center justify-center gap-3 h-full p-6 bg-white  border border-gray-200  rounded-xl shadow-sm hover:shadow-md transition">
                <h5 class="text-4xl font-bold text-gray-900  mb-2">
                    10
                </h5>
                <p class="text-sm text-gray-500  uppercase tracking-wide">
                    Days Remaining
                </p>
                <div class="w-full bg-gray-200 rounded-full h-2.5 ">
                    <div class="bg-red-700 h-2.5 rounded-full" style="width: 20%"></div>
                </div>
                <div class="flex w-full justify-between">
                    <span class="text-xs font-medium text-gray-700 ">Progress</span>
                    <span class="text-xs font-medium text-gray-700 ">20%</span>
                </div>
            </div>
        </div>
        {{-- Date Selector --}}
        <ul class="text-sm max-w-5xl mx-auto font-medium rounded-xl text-center text-gray-500 shadow-sm flex sm:justify-center   overflow-hidden">
            @for($i = 0; $i < 6; $i++) <li class="flex-1">
                <button class="w-full transition-colors duration-300 px-4 py-2 border border-gray-200  text-black hover:border-gray-800 cursor-pointer focus:ring-4
                {{ $i === 0 ? 'rounded-l-xl' : '' }}
                {{ $i === 5 ? 'rounded-r-xl' : '' }}">
                    Day {{ $i + 1 }}<br />
                </button>
                </li>
                @endfor
        </ul>

        <div class="bg-white  rounded-2xl shadow-sm border border-zinc-200 p-6">
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">Guest List </flux:heading>
                <flux:modal.trigger name="form-guest-modal">
                    <flux:button size="sm" variant="primary">
                        + Add Guest
                    </flux:button>
                </flux:modal.trigger>
            </div>
            <div class="justify-between mb-6 grid grid-flow-col grid-cols-3 gap-4 px-5 py">
                <div class="flex gap-4 col-span-2">
                    <div class="flex justify-between gap-4">
                        <flux:label class="text-sm font-medium text-gray-900 ">Search</flux:label>
                        <flux:input type="search" placeholder="Search guests..." wire:model.live.debounce.350ms="searchQuery" />
                    </div>
                    <div class="flex justify-between gap-4">
                        <flux:label class="text-sm font-medium text-gray-900 ">Status</flux:label>
                        <flux:select wire:model.live="searchStatus" placeholder="Select Status ...">
                            <flux:select.option value="">All</flux:select.option>
                            <flux:select.option value="invited">Invited</flux:select.option>
                            <flux:select.option value="checked_in">Checked In</flux:select.option>
                            <flux:select.option value="not_checked_in">Not Checked In</flux:select.option>
                            <flux:select.option value="cancelled">Cancelled</flux:select.option>
                        </flux:select>
                    </div>
                </div>
                <div class="flex justify-between gap-4">
                    <flux:label class="text-sm font-medium text-gray-900 ">Sort</flux:label>
                    <flux:select wire:model.live="filterSort" placeholder="Sort By">
                        <flux:select.option value="name">Name</flux:select.option>
                        <flux:select.option value="email">Email</flux:select.option>
                        <flux:select.option value="organization">Organization</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="filterOrder" placeholder="Order">
                        <flux:select.option value="asc">ASC</flux:select.option>
                        <flux:select.option value="desc">DESC</flux:select.option>
                    </flux:select>
                </div>
            </div>

            @if (!empty($this->guests) && count($this->guests) > 0)
            <div class="overflow-x-auto rounded-lg border border-zinc-200 ">
                <table class="min-w-full text-sm text-left text-gray-600 ">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 ">
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Jabatan</th>
                            <th class="px-6 py-3">Phone</th>
                            <th class="px-6 py-3">Organization</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->guests as $guest)
                        <tr wire:key="{{ $guest['id'] }}-{{ $guest['confirm_attendance'] }}" class="border-b border-gray-100 hover:bg-gray-50 transition">
                            <td class="px-6 py-3 font-medium text-gray-900">
                                {{ $guest['name'] }}
                            </td>
                            <td class="px-6 py-3">{{ $guest['jabatan'] }}</td>
                            <td class="px-6 py-3">{{ $guest['phone'] }}</td>
                            <td class="px-6 py-3">{{ $guest['organization'] ?? '-' }}</td>
                            <td class="px-6 py-3">
                               <flux:badge :color="match($guest['status']){
                                   'registered' => 'blue',
                                   'checked_in' => 'green',
                                   'not_checked_in' => 'red',
                                   'cancelled' => 'gray',
                               }" size="sm" class="px-5 rounded-xl"  >
                                    {{ Str::title(str_replace('_', ' ', $guest['status'])) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-3 text-right flex justify-end gap-2">
                                <flux:modal.trigger name="qr-modal-{{ $guest['id'] }}">
                                    @if (!empty($guest['qr_generated']))
                                    <flux:button class="text-sm" :disabled="$guest['qr_generated'] == null" variant="primary" size="sm">
                                        Show QR
                                    </flux:button>
                                    @else
                                    <flux:tooltip content="QR Code not generated yet">
                                        <div>
                                            <flux:modal.trigger name="qr">
                                                <flux:button disabled size="sm">Show QR</flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    </flux:tooltip>
                                    @endif

                                </flux:modal.trigger>
                                <flux:button size="sm" variant="ghost" color="blue" wire:click="edit({{ $guest['id'] }})">
                                    Edit
                                </flux:button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-10 text-gray-400">
                No guests found for this event.
            </div>
            @endif
        </div>
    </div>
</div>

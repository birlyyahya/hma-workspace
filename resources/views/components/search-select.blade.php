@props([
    'model',
    'options' => [],
    'multiple' => false,
    'placeholder' => 'Pilih...',
    'searchPlaceholder' => 'Cari...',
    'avatar' => false,
    'live' => false,
])

{{--
    Reusable searchable select (tanpa library). Mendukung dua mode:
      - single : terikat ke properti skalar Livewire
      - multiple (tag) : terikat ke properti array Livewire

    Binding via wire:model (entangle), jadi cukup berikan nama propertinya.

    Penggunaan:
        <x-search-select model="company_id"
            :options="$companies->map(fn($c) => ['value' => $c['id'], 'label' => $c['name']])->all()"
            placeholder="Cari perusahaan..." />

        <x-search-select model="team_user" multiple :avatar="true"
            :options="$users->map(fn($u) => ['value' => $u->id, 'label' => $u->name])->all()" />
--}}

@php
    $jsOptions = collect($options)->map(fn ($o) => [
        'value' => $o['value'],
        'label' => (string) $o['label'],
    ])->values()->all();
@endphp

<div
    x-data="{
        open: false,
        query: '',
        multiple: {{ $multiple ? 'true' : 'false' }},
        options: @js($jsOptions),
        @if($live)
        selected: @entangle($model).live,
        @else
        selected: @entangle($model),
        @endif
        labelFor(value) {
            const o = this.options.find((o) => o.value == value);
            return o ? o.label : '';
        },
        get hasSelection() {
            return this.multiple
                ? Array.isArray(this.selected) && this.selected.length > 0
                : this.selected !== null && this.selected !== '' && this.selected !== undefined;
        },
        toggle(value) {
            if (this.multiple) {
                const arr = Array.isArray(this.selected) ? [...this.selected] : [];
                const i = arr.findIndex((v) => v == value);
                if (i === -1) { arr.push(value); } else { arr.splice(i, 1); }
                this.selected = arr;
            } else {
                this.selected = value;
                this.open = false;
                this.query = '';
            }
        },
        isSelected(value) {
            if (this.multiple) {
                return Array.isArray(this.selected) && this.selected.some((v) => v == value);
            }
            return this.hasSelection && this.selected == value;
        },
        remove(value) {
            if (this.multiple) {
                this.selected = (this.selected || []).filter((v) => v != value);
            } else {
                this.selected = null;
            }
        },
        matches(label) {
            if (!this.query) { return true; }
            return label.toLowerCase().includes(this.query.toLowerCase());
        },
        noResults() {
            if (!this.query) { return false; }
            const q = this.query.toLowerCase();
            return !this.options.some((o) => o.label.toLowerCase().includes(q));
        },
    }"
    @click.away="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    {{-- Trigger --}}
    <div
        @click="open = !open; if (open) $nextTick(() => $refs.search.focus())"
        class="flex min-h-10 w-full cursor-pointer flex-wrap items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm shadow-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 dark:border-zinc-700 dark:bg-zinc-900"
    >
        {{-- Chips (multiple) --}}
        <template x-if="multiple">
            <template x-for="value in (selected || [])" :key="value">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <span x-text="labelFor(value)"></span>
                    <button type="button" @click.stop="remove(value)"
                        class="grid h-4 w-4 place-items-center rounded-full text-zinc-400 hover:bg-zinc-200 hover:text-red-600 dark:hover:bg-zinc-700">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </span>
            </template>
        </template>

        {{-- Single selected label --}}
        <span x-show="!multiple && hasSelection" x-text="labelFor(selected)" class="truncate text-zinc-900 dark:text-zinc-100"></span>

        {{-- Placeholder --}}
        <span x-show="!hasSelection" class="text-zinc-400">{{ $placeholder }}</span>

        <svg class="ml-auto h-4 w-4 shrink-0 text-zinc-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l3.25 3.5a.75.75 0 1 1-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 0 1-1.1-1.02l3.25-3.5A.75.75 0 0 1 10 3Zm-3.76 9.2a.75.75 0 0 1 1.06.04L10 15.148l2.7-2.908a.75.75 0 1 1 1.1 1.02l-3.25 3.5a.75.75 0 0 1-1.1 0l-3.25-3.5a.75.75 0 0 1 .04-1.06Z" clip-rule="evenodd"/></svg>
    </div>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-cloak
        x-transition.origin.top
        class="absolute left-0 right-0 z-30 mt-1 rounded-xl bg-white p-1 shadow-lg ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700"
    >
        <div class="p-1">
            <input
                x-ref="search"
                x-model="query"
                type="text"
                placeholder="{{ $searchPlaceholder }}"
                class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 placeholder:text-zinc-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
            />
        </div>
        <div class="max-h-60 overflow-y-auto">
            @foreach($options as $opt)
                <button
                    type="button"
                    wire:key="ss-{{ $model }}-{{ $opt['value'] }}"
                    x-show="matches(@js((string) $opt['label']))"
                    @click="toggle(@js($opt['value']))"
                    :class="isSelected(@js($opt['value'])) ? 'bg-zinc-50 dark:bg-zinc-800' : ''"
                    class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                >
                    <span class="inline-flex min-w-0 items-center gap-2">
                        @if($avatar)
                            <flux:avatar circle name="{{ $opt['label'] }}" size="xs" />
                        @endif
                        <span class="truncate text-zinc-800 dark:text-zinc-100">{{ $opt['label'] }}</span>
                    </span>
                    <svg x-show="isSelected(@js($opt['value']))" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.79a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                </button>
            @endforeach
            <p x-show="noResults()" class="px-3 py-2 text-xs text-zinc-500">Tidak ada hasil.</p>
        </div>
    </div>
</div>

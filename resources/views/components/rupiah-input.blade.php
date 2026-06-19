@props([
    'model',
    'placeholder' => '0',
    'prefix' => 'Rp',
])

{{--
    Reusable rupiah input. Menampilkan angka berformat ribuan (1.000.000) saat
    diketik, namun menyimpan angka mentah (digit murni) ke properti Livewire.

    Penggunaan:
        <x-rupiah-input model="value" placeholder="8.000.000.000" />
        <x-rupiah-input model="form.price" />
--}}

<div
    x-data="{
        display: '',
        init() {
            const raw = String($wire.get(@js($model)) ?? '').replace(/\D/g, '');
            this.display = raw ? new Intl.NumberFormat('id-ID').format(raw) : '';
        },
        format() {
            const digits = this.display.replace(/\D/g, '');
            this.display = digits ? new Intl.NumberFormat('id-ID').format(digits) : '';
            $wire.set(@js($model), digits, false);
        },
    }"
    class="relative"
>
    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $prefix }}</span>
    <input
        type="text"
        inputmode="numeric"
        x-model="display"
        @input="format()"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-zinc-200 bg-white py-2 pl-9 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100']) }}
    />
</div>

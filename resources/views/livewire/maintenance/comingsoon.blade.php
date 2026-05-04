<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-zinc-50 to-zinc-100 px-4">

    <div  x-data="{click:0}" class="max-w-xl w-full text-center">

        <!-- 🔹 Icon / Logo -->
        <div class="flex justify-center mb-6">
            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-white shadow-md">
                <flux:icon name="sparkles" class="w-8 h-8 text-blue-500" />
            </div>
        </div>

        <!-- 🔹 Title -->
        <h1 class="text-3xl md:text-4xl font-bold text-zinc-900 tracking-tight">
            Coming Soon 🚀
        </h1>

        <!-- 🔹 Subtitle -->
        <p class="mt-3 text-zinc-600 text-sm md:text-base">
            Fitur ini sedang dalam tahap pengembangan dan akan segera tersedia.
            Nantikan update terbaru dari kami.
        </p>

        <!-- 🔹 Divider -->
        <div class="mt-6 flex items-center justify-center gap-2">
            <span class="h-px w-10 bg-zinc-200"></span>
            <span class="text-xs text-zinc-400 uppercase tracking-wide">Stay Tuned</span>
            <span class="h-px w-10 bg-zinc-200"></span>
        </div>

        <!-- 🔹 Action -->
        <div class="mt-8 flex justify-center gap-3">

            <a href="/" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 transition">
                <flux:icon name="arrow-left" class="w-4 h-4" />
                Kembali
            </a>

            <button x-on:click="click += 1" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition">
                <flux:icon name="cursor-arrow-ripple" class="w-4 h-4" />
                Click Me
            </button>

        </div>
        <p x-show="click === 1 || click === 2 " class="mt-4">anjay nurut </p>
        <p x-show="click === 1 || click === 2" class="mt-4">atau klik lagi?</p>
        <p x-show="click === 2" class="mt-4">wkwkwkkw</p>
        <p x-show="click === 8" class="mt-4">masih?</p>
        <p x-show="click === 16" class="mt-4">klik kembali bos</p>

        <!-- 🔹 Footer -->
        <p class="mt-10 text-xs text-zinc-400">
            © {{ date('Y') }} Hanatekindo • All rights reserved
        </p>

    </div>
</div>

@props([
    // Definisi kolom: [['key' => 'name', 'header' => 'Nama', 'required' => true, 'type' => 'string'], ...]
    'columns' => [],
    // Contoh isian untuk template: [['name' => 'Pipa PVC', 'type' => 'Barang', ...], ...]
    'example' => [],
    'templateName' => 'template.xlsx',
    // Nama method Livewire yang menerima array hasil parsing.
    'onImport' => 'importParsed',
    // Teks tombol import.
    'submitLabel' => 'Import',
])

{{--
    Komponen import Excel reusable. Parsing dilakukan penuh di frontend (SheetJS)
    lalu array JSON dikirim ke method Livewire {{ $onImport }}. Bungkus wire:ignore
    agar Livewire tidak mengganggu state Alpine saat morphing.

    Reset state dari sisi server: dispatch event 'excel-import-reset'.
--}}
<div
    wire:ignore
    x-data="excelImport({ columns: @js($columns), example: @js($example), templateName: @js($templateName), onImport: @js($onImport) })"
    x-on:excel-import-reset.window="reset()"
    class="space-y-4"
>
    {{-- Petunjuk + unduh template --}}
    <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
        <div class="flex items-start gap-2">
            <flux:icon.information-circle class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
            <div class="space-y-2 min-w-0">
                <flux:text class="text-xs text-zinc-600 leading-relaxed">
                    Unduh template, isi sesuai kolom, lalu unggah kembali. File dibaca langsung di browser —
                    Anda bisa memeriksa hasilnya sebelum menyimpan.
                </flux:text>
                <flux:button type="button" x-on:click="downloadTemplate()" variant="ghost" size="sm" icon="arrow-down-tray">
                    Unduh Template Excel
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Pilih file --}}
    <flux:field>
        <flux:label badge="Wajib">File Excel</flux:label>
        <input type="file" x-ref="fileInput" accept=".xlsx,.xls,.csv" x-on:change="handleFile($event)"
            class="block w-full text-sm text-zinc-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100 file:cursor-pointer cursor-pointer" />

        <div x-show="parsing" class="mt-2">
            <flux:text class="text-xs text-zinc-500">Membaca file...</flux:text>
        </div>

        <div x-show="fileName && !parsing" x-cloak class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 border border-emerald-100 p-3">
            <flux:icon.document-check class="w-5 h-5 text-emerald-600 shrink-0" />
            <flux:text class="text-sm text-emerald-800 truncate" x-text="fileName"></flux:text>
        </div>
    </flux:field>

    {{-- Daftar error --}}
    <div x-show="hasErrors" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3">
        <p class="flex items-center gap-1.5 text-sm font-medium text-red-800">
            <flux:icon.exclamation-triangle class="w-4 h-4" />
            Perbaiki masalah berikut lalu unggah ulang:
        </p>
        <ul class="mt-2 space-y-0.5 text-xs text-red-700 list-disc list-inside max-h-32 overflow-y-auto">
            <template x-for="(error, index) in errors" :key="index">
                <li x-text="error"></li>
            </template>
        </ul>
    </div>

    {{-- Preview data --}}
    <div x-show="rows.length > 0 && !hasErrors" x-cloak class="space-y-2">
        <div class="flex items-center justify-between">
            <flux:heading size="sm" class="font-medium text-zinc-900">
                Pratinjau <span class="text-zinc-400 font-normal" x-text="'(' + rows.length + ' baris)'"></span>
            </flux:heading>
            <button type="button" x-on:click="reset()" class="text-xs text-zinc-500 hover:text-zinc-800 font-medium cursor-pointer">
                Bersihkan
            </button>
        </div>
        <div class="border border-zinc-200 rounded-xl overflow-hidden">
            <div class="max-h-[45vh] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 sticky top-0 z-10">
                        <tr class="text-left text-[11px] uppercase tracking-wide text-zinc-500">
                            <th class="px-3 py-2.5 font-medium w-10 text-right">#</th>
                            <template x-for="column in columns" :key="column.key">
                                <th class="px-3 py-2.5 font-medium whitespace-nowrap" x-text="column.header"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <template x-for="(row, index) in rows" :key="index">
                            <tr class="hover:bg-zinc-50/60">
                                <td class="px-3 py-2 text-right text-zinc-400" x-text="index + 1"></td>
                                <template x-for="column in columns" :key="column.key">
                                    <td class="px-3 py-2 text-zinc-800 whitespace-nowrap" x-text="row[column.key]"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Aksi --}}
    <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4">
        <flux:modal.close>
            <flux:button type="button" variant="ghost">Tutup</flux:button>
        </flux:modal.close>
        <flux:button type="button" variant="primary" icon="arrow-up-tray"
            x-on:click="submit()" x-bind:disabled="!canImport">
            <span x-show="!submitting">{{ $submitLabel }} <span x-show="rows.length > 0" x-text="'(' + rows.length + ')'"></span></span>
            <span x-show="submitting" x-cloak>Menyimpan...</span>
        </flux:button>
    </div>
</div>

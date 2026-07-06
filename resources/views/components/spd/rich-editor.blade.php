@props(['model', 'placeholder' => ''])

{{--
    Editor rich-text bullet/list-only untuk form SPD (CKEditor, bundled via
    resources/js/app.js). Toolbar sengaja dibatasi hanya ordered/bullet list.
    Nilai tersimpan sebagai HTML dan di-entangle ke properti Livewire {{ $model }}.
--}}
<div
    wire:ignore
    x-data="spdRichEditor(@entangle($model))"
    class="spd-editor overflow-hidden rounded-xl border border-zinc-200 bg-white focus-within:border-zinc-400"
>
    <div x-ref="editor" data-placeholder="{{ $placeholder }}"></div>
</div>

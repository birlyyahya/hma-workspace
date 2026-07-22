@props(['model', 'placeholder' => 'Tulis deskripsi...'])

{{--
    Field CKEditor generik (bundled via resources/js/app.js, bukan CDN).
    Alpine.data('ckeditorField', ...) didaftarkan global di app.js agar tidak
    perlu duplikasi @script per komponen yang memakainya.
--}}
<div
    wire:ignore
    x-data="ckeditorField(@entangle($model), @js($placeholder))"
    {{ $attributes->class(['ckeditor-field overflow-hidden rounded-xl border border-zinc-200 bg-white focus-within:border-zinc-400']) }}
>
    <div x-ref="editor"></div>
</div>

@assets
<style>
    .ckeditor-field .ck.ck-toolbar {
        border: none;
        border-bottom: 1px solid #e4e4e7;
        background: #fafafa;
    }

    .ckeditor-field .ck.ck-editor__editable_inline {
        border: none !important;
        box-shadow: none !important;
        font-size: 0.875rem;
        min-height: 120px;
        max-height: 320px;
        overflow-y: auto;
    }

    .ckeditor-field .ck .ck-placeholder::before {
        color: #a1a1aa;
    }
</style>
@endassets

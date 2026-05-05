@php
    /**
     * Attachment card / image thumbnail.
     *
     * @var array $file ['id', 'filename', 'size', 'type', 'url']
     * @var string $variant 'card' (default) or 'thumb'
     */
    $file = $file ?? [];
    $variant = $variant ?? 'card';
    $filename = $file['filename'] ?? 'untitled';
    $url = config('services.api_izin').$file['path'] ?? '#';
    $size = $file['size'] ?? null;
    $mime = $file['type'] ?? null;
    $isImage = isImageFile($filename, $mime);
    $meta = fileExtMeta($filename);
@endphp

@if ($isImage && $variant === 'thumb')
    <button
        type="button"
        @click="$dispatch('open-lightbox', { url: '{{ $url }}', name: '{{ addslashes($filename) }}' })"
        class="group relative h-24 w-24 shrink-0 overflow-hidden rounded-xl ring-1 ring-zinc-200 bg-zinc-100 hover:ring-zinc-300 transition"
        title="{{ $filename }}"
    >
        <img src="{{ $url }}" alt="{{ $filename }}" loading="lazy"
             class="h-full w-full object-cover transition group-hover:scale-105" />
        <span class="pointer-events-none absolute inset-x-0 bottom-0 truncate bg-linear-to-t from-black/60 to-transparent px-2 py-1 text-[10px] font-medium text-white">
            {{ $filename }}
        </span>
    </button>
@else
    <div class="group flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 transition hover:border-zinc-300 hover:bg-zinc-50">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-[10px] font-bold {{ $meta['bg'] }} {{ $meta['text'] }} ring-1 ring-inset ring-current/10">
            {{ $meta['label'] }}
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-zinc-900">{{ $filename }}</p>
            <p class="text-xs text-zinc-500">{{ formatFileSize($size) }}</p>
        </div>
        <div class="flex items-center gap-1 opacity-60 transition group-hover:opacity-100">
            @if ($isImage)
                <button
                    type="button"
                    @click="$dispatch('open-lightbox', { url: '{{ $url }}', name: '{{ addslashes($filename) }}' })"
                    class="grid h-8 w-8 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"
                    title="Preview"
                >
                    <flux:icon name="eye" class="h-4 w-4" />
                </button>
            @endif
            <a
                href="{{ $url }}"
                target="_blank"
                download="{{ $filename }}"
                class="grid h-8 w-8 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"
                title="Download"
            >
                <flux:icon name="arrow-down-tray" class="h-4 w-4" />
            </a>
        </div>
    </div>
@endif

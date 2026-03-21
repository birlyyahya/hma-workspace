<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
     use WithFileUploads;

    public $content;
    public $title;
    public $file;
    public $category;

    public function contentChanged($editorId, $content)
    {
        // $editorId is the id use when you initiated the livewire component
        // $content is the raw text editor content

        // save to the local variable...
        $this->content = $content;
    }
}; ?>

<div>
    <div class="relative w-full py-6 px-2">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item class="font-normal !text-gray-300" href="#">Knowledge</flux:breadcrumbs.item>
            <flux:breadcrumbs.item class="!text-gray-400 font-normal" href="#">Article</flux:breadcrumbs.item>
            <flux:breadcrumbs.item class="font-normal">Create new Article</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="p-6 rounded-xl space-y-6 bg-white">
            <flux:heading size="lg" level="1" class="mb-6">{{ __('Create new Article') }}</flux:heading>

            <div class="space-y-3">
                <flux:input label="Title" wire:model.live="title"></flux:input>
                <flux:text class="text-xs">Title will be used as slug</flux:text>
            </div>

            <div wire:ignore>
                <select id="category" multiple="multiple" class="select2 form-select" placeholder="select a team">
                    <option value="tips">Tips</option>
                    <option value="tutorial">Tutorial</option>
                    <option value="insight">Insight</option>
                    <option value="workflow">Workflow</option>
                    <option value="approval">Approval</option>
                    <option value="efesiensi">Efesiensi</option>
                </select>
            </div>

            <div class="space-y-3">
                <flux:heading>Featrued Image</flux:heading>
                <div class="flex items-center justify-center w-1/2">

                    <label for="dropzone-file" class="
                flex flex-col items-center justify-center
                w-full h-64 bg-white border rounded-lg cursor-pointer overflow-hidden
                {{ $file ? '' : 'border-dashed border-red-700 hover:bg-red-50' }}
            ">
                        @if($file)
                        <img class="w-full h-full object-cover" src="{{ $file->temporaryUrl() }}" alt="">
                        @else
                        <div class="flex flex-col items-center justify-center text-body">
                            <flux:icon.photo class="w-10 h-10 mb-4 text-red-700" />
                            <p class="text-xs text-gray-400">
                                Image Only (MAX. 800x400px)
                            </p>
                        </div>
                        @endif

                        <input id="dropzone-file" wire:model="file" type="file" class="hidden" />

                    </label>

                </div>
            </div>
            <flux:button type="submit" color="primary">Create</flux:button>
        </div>

        <div class="space-y-4 p-6 bg-white rounded-lg">
            <flux:heading size="lg" level="1" class="mb-6">Preview</flux:heading>

            <flux:heading size="xl" level="1" class="mb-6">{{ $title ?? '' }}</flux:heading>
            @if($content)
            {{-- {{ dd($content) }} --}}
            {{ $content }}
            <br>
            <br>
            <br>
            {!! $content !!}
            @endif

        </div>

    </div>
    <div x-data="setupEditor(
    $wire.entangle('{{ $content }}').defer
  )" x-init="() => init($refs.editor)" wire:ignore>
        <div x-ref="editor"></div>
    </div>

</div>
@script
<script>
    const el = $('#category');
    el.select2({
        width: '100%'
        , placeholder: "Select a category"
        , tags: true
    });

</script>
@endscript

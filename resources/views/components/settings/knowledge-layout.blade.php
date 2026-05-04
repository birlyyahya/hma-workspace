@props(['heading' => '', 'subheading' => ''])

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Knowledge') }}">
            <flux:navlist.item icon="home" :href="route('knowledge')" wire:navigate>{{ __('Hub') }}</flux:navlist.item>
            <flux:navlist.item icon="megaphone" :href="route('knowledge.announcements')" wire:navigate>{{ __('Announcements') }}</flux:navlist.item>
            <flux:navlist.item icon="newspaper" :href="route('knowledge.articles')" wire:navigate>{{ __('Articles') }}</flux:navlist.item>
            <flux:navlist.item icon="shield-check" :href="route('knowledge.policies')" wire:navigate>{{ __('Policies & Rules') }}</flux:navlist.item>
            <flux:navlist.item icon="book-open" :href="route('knowledge.documentation')" wire:navigate>{{ __('Documentation / Guides') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <div class="flex justify-between items-center gap-5">
            <div>
                <flux:heading>{{ $heading }}</flux:heading>
                <flux:subheading>{{ $subheading }}</flux:subheading>
            </div>
            @isset($action)
                {{ $action }}
            @endisset
        </div>

        <flux:separator variant="subtle" class="my-4" />

        @isset($toolbar)
            <div class="mb-4">{{ $toolbar }}</div>
        @endisset

        <div class="mt-2 w-full">
            {{ $slot }}
        </div>
    </div>
</div>

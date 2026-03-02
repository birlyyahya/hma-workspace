<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('knowledge.announcements')" wire:navigate>{{ __('Announcements') }}</flux:navlist.item>
            <flux:navlist.item :href="route('knowledge.articles')" wire:navigate>{{ __('Articles') }}</flux:navlist.item>
            <flux:navlist.item :href="route('knowledge.policies')" wire:navigate>{{ __('Policies & Rules') }}</flux:navlist.item>
            <flux:navlist.item :href="route('knowledge.documentation')" wire:navigate>{{ __('Documentation / Guides') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <flux:separator variant="subtle" class="my-4" />

        @isset($search)
        <flux:input icon="magnifying-glass" placeholder="{{ __('Search '.$search.'...') }}" class="mt-4 w-full rounded-full" />
        @endisset

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>

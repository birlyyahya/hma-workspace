<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-slate-100 antialiased text-slate-900">
    <x-toaster-hub />

    {{ $slot }}

    @fluxScripts
    @livewireScripts
</body>
</html>

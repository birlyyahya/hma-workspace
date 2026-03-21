<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="img/logo/logo-hma2.png" sizes="any">
    <link rel="icon" href="img/logo/logo-hma2.png" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Poppins:400,500,600" rel="stylesheet" />
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}

    {{-- @livewireStyles --}}
</head>
<body class="bg-gray-100">

    <flux:modal.trigger name="form-izin-modal">
        <flux:button icon="plus-circle" href="" variant="primary" class="cursor-pointer w-full sm:w-auto">
            Pengajuan Izin
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="form-izin-modal">
        <p>ha`</p>
    </flux:modal>

    @fluxScripts
</body>

</html>

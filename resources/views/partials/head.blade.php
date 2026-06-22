<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="{{ asset('img/logo/logo-hma2.png') }}" sizes="any">
<link rel="icon" href="{{ asset('img/logo/logo-hma2.png') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('img/logo/logo-hma2.png') }}">

{{-- Open Graph / link preview (WhatsApp, Telegram, dll.) --}}
<meta property="og:type" content="website" />
<meta property="og:site_name" content="HMA Workspace" />
<meta property="og:title" content="{{ $title ?? config('app.name') }}" />
<meta property="og:description" content="Portal internal Hana Tekindo — kelola proyek, DAR, perizinan, dan knowledge dalam satu tempat." />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:image" content="{{ asset('img/logo/og-image.png') }}" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $title ?? config('app.name') }}" />
<meta name="twitter:description" content="Portal internal Hana Tekindo — kelola proyek, DAR, perizinan, dan knowledge dalam satu tempat." />
<meta name="twitter:image" content="{{ asset('img/logo/og-image.png') }}" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=Poppins:400,500,600" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

@stack('styles')

@stack('link')

<script>
    // Force light appearance and disable automatic dark toggles.
    (function() {
        try {
            localStorage.setItem('flux.appearance', 'light');
        } catch (e) {
            // ignore
        }

        // Remove any existing dark class immediately
        try {
            document.documentElement.classList.remove('dark');
        } catch (e) {}

        // Prevent scripts from adding/removing/toggling the `dark` token on class lists
        (function() {
            const proto = DOMTokenList.prototype;
            const add = proto.add;
            const remove = proto.remove;
            const toggle = proto.toggle;

            proto.add = function(...tokens) {
                if (tokens.includes('dark')) return this;
                return add.apply(this, tokens);
            };

            proto.remove = function(...tokens) {
                if (tokens.includes('dark')) return this;
                return remove.apply(this, tokens);
            };

            proto.toggle = function(token, force) {
                if (token === 'dark') return this;
                return toggle.apply(this, arguments);
            };
        })();

        // Make matchMedia report 'prefers-color-scheme: dark' as false for JS that checks it
        try {
            const realMatch = window.matchMedia.bind(window);
            window.matchMedia = function(query) {
                if (query === '(prefers-color-scheme: dark)') {
                    return {
                        matches: false
                        , media: query
                        , addEventListener: function() {}
                        , removeEventListener: function() {}
                        , onchange: null
                    };
                }
                return realMatch(query);
            };
        } catch (e) {}
    })();

</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])

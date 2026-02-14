<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=Poppins:400,500,600" rel="stylesheet" />

<script>
	// Force light appearance and disable automatic dark toggles.
	(function () {
		try {
			localStorage.setItem('flux.appearance', 'light');
		} catch (e) {
			// ignore
		}

		// Remove any existing dark class immediately
		try { document.documentElement.classList.remove('dark'); } catch (e) {}

		// Prevent scripts from adding/removing/toggling the `dark` token on class lists
		(function () {
			const proto = DOMTokenList.prototype;
			const add = proto.add;
			const remove = proto.remove;
			const toggle = proto.toggle;

			proto.add = function (...tokens) {
				if (tokens.includes('dark')) return this;
				return add.apply(this, tokens);
			};

			proto.remove = function (...tokens) {
				if (tokens.includes('dark')) return this;
				return remove.apply(this, tokens);
			};

			proto.toggle = function (token, force) {
				if (token === 'dark') return this;
				return toggle.apply(this, arguments);
			};
		})();

		// Make matchMedia report 'prefers-color-scheme: dark' as false for JS that checks it
		try {
			const realMatch = window.matchMedia.bind(window);
			window.matchMedia = function (query) {
				if (query === '(prefers-color-scheme: dark)') {
					return { matches: false, media: query, addEventListener: function(){}, removeEventListener: function(){}, onchange: null };
				}
				return realMatch(query);
			};
		} catch (e) {}
	})();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])

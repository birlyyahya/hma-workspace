<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('img/logo/logo-hma2.png') }}" sizes="any">
<link rel="icon" href="{{ asset('img/logo/logo-hma2.png') }}" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Sedang Dalam Pemeliharaan — HMA Workspace</title>
    <style>
        :root {
            --bg-from: #f8fafc;
            --bg-to: #eef2f7;
            --card: #ffffff;
            --text: #18181b;
            --muted: #71717a;
            --border: #e4e4e7;
            --brand: #2563eb;
            --brand-soft: #dbeafe;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-from: #09090b;
                --bg-to: #18181b;
                --card: #18181b;
                --text: #fafafa;
                --muted: #a1a1aa;
                --border: #27272a;
                --brand: #3b82f6;
                --brand-soft: #1e3a8a33;
            }
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg-from), var(--bg-to));
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 30rem;
            text-align: center;
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            animation: float 4s ease-in-out infinite;
        }

        .logo-wrap img {
            height: 4.5rem;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.15));
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .progress {
            position: relative;
            height: 4px;
            width: 12rem;
            margin: 2rem auto 1rem;
            border-radius: 999px;
            background: var(--border);
            overflow: hidden;
        }

        .progress::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 40%;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, var(--brand), transparent);
            animation: slide 1.6s ease-in-out infinite;
        }

        @keyframes slide {
            0% { left: -40%; }
            100% { left: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .logo-wrap { animation: none; }
            .progress::after { animation: none; left: 0; width: 100%; }
        }

        .badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
            background: var(--brand-soft);
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 1.75rem;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 0.75rem;
        }

        p.lead {
            font-size: 0.95rem;
            line-height: 1.65;
            color: var(--muted);
            margin: 0 auto;
            max-width: 26rem;
        }

        .note {
            font-size: 0.8rem;
            color: var(--muted);
        }

        footer {
            margin-top: 2.5rem;
            font-size: 0.72rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="logo-wrap">
            <img src="{{ asset('img/logo/logo-hma2.png') }}" alt="HMA Workspace">
        </div>

        <div class="badge">Pemeliharaan</div>

        <h1>Sedang Dalam Perbaikan</h1>

        <p class="lead">
            HMA Workspace untuk sementara tidak dapat diakses karena sedang dalam proses
            pemeliharaan terjadwal. Kami sedang melakukan peningkatan agar layanan menjadi
            lebih baik. Silakan kembali beberapa saat lagi.
        </p>

        <div class="progress" aria-hidden="true"></div>

        <p class="note">Halaman ini akan menyegarkan otomatis setiap 60 detik.</p>

        <footer>
            &copy; {{ date('Y') }} Hanatekindo &bull; All rights reserved
        </footer>
    </main>
</body>
</html>

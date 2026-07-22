<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Cetak Timeline Project' }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }

        * {
            box-sizing: border-box;
            /* Pertahankan warna latar (bar gantt) saat dicetak — tanpa ini
               browser membuang semua background sehingga bar jadi kosong. */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            color: #18181b;
        }

        body {
            background: #e4e4e7;
            padding: 28px 20px;
            font-size: 12px;
            line-height: 1.4;
        }

        .sheet {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            padding: 28px 32px;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        .toolbar {
            max-width: 1400px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            border: 1px solid #d4d4d8;
            background: #fff;
            color: #18181b;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn:hover { background: #f4f4f5; }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }

        @media print {
            body { background: #fff; padding: 0; font-size: 11px; }
            .sheet { border: none; border-radius: 0; max-width: 100%; padding: 0; box-shadow: none; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ url()->previous() }}" class="btn">Kembali</a>
        <button onclick="window.print()" class="btn btn-primary">Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        {{ $slot }}
    </div>
</body>
</html>

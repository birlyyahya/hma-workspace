<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Laporan Cash Advance' }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }

        body {
            background: #f4f4f5;
            padding: 24px;
            font-size: 13px;
            line-height: 1.4;
        }

        .sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 32px 36px;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
        }

        .toolbar {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #d4d4d8;
            background: #fff;
            color: #18181b;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }

        .report-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        .report-head h1 { font-size: 18px; margin: 0 0 2px; }
        .report-head p { margin: 0; color: #52525b; font-size: 12px; }
        .report-head .company { text-align: right; }
        .report-head .company strong { display: block; font-size: 14px; }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta td:first-child { width: 160px; color: #52525b; }
        .meta td:nth-child(2) { width: 12px; }

        .summary {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary .box {
            flex: 1;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .summary .box span { display: block; font-size: 11px; color: #71717a; }
        .summary .box strong { font-size: 15px; }

        table.ledger {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        table.ledger th, table.ledger td {
            border: 1px solid #d4d4d8;
            padding: 6px 8px;
            font-size: 12px;
        }
        table.ledger th {
            background: #f4f4f5;
            text-align: left;
            font-weight: 600;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .neg { color: #dc2626; }
        .pos { color: #16a34a; }

        .callout {
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .signoff { margin-top: 36px; display: flex; justify-content: space-between; }
        .signoff div { width: 200px; text-align: center; }
        .signoff .line { margin-top: 56px; border-top: 1px solid #111; padding-top: 4px; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: none; border-radius: 0; max-width: 100%; padding: 0; }
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

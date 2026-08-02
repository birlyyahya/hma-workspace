<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lampiran Bukti</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }

        .label {
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
        }

        .frame { text-align: center; }
        .frame img { max-width: 100%; max-height: 165mm; }
    </style>
</head>
<body>
    <p class="label">No. {{ $label }} &mdash; {{ $title }}</p>
    <div class="frame">
        <img src="{{ $image }}" alt="Bukti transaksi">
    </div>
</body>
</html>

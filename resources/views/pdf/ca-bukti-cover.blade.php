<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lampiran Bukti</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }

        .label {
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 8px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }

        {{-- max-width + max-height (tanpa width tetap) menjaga rasio gambar
             sambil memuatnya sepenuhnya dalam sisa 1 halaman — tinggi dibatasi
             agak konservatif (bukan mepet ke batas halaman) supaya tetap aman
             walau label deskripsi panjang sampai wrap 2-3 baris. --}}
        .frame { text-align: center; }
        .frame img { max-width: 100%; max-height: 170mm; }
    </style>
</head>
<body>
    <p class="label">No. {{ $label }} &mdash; {{ $title }}</p>
    <div class="frame">
        <img src="{{ $image }}" alt="Bukti transaksi">
    </div>
</body>
</html>

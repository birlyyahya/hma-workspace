<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan Izin</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #18181b; font-size: 10px; }
        h1 { margin: 0 0 18px; font-size: 18px; }
        h2 { margin: 16px 0 7px; padding: 7px 9px; background: #f4f4f5; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d4d4d8; padding: 6px 7px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #fafafa; font-weight: bold; }
        .date { width: 14%; }
        .name { width: 22%; }
        .reason { width: 30%; }
        .note { width: 34%; }
    </style>
</head>
<body>
    <h1>Laporan Pengajuan Izin</h1>

    @forelse ($groups as $group)
        <h2>{{ $group['label'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th class="date">Tanggal</th>
                    <th class="name">Nama</th>
                    <th class="reason">Alasan</th>
                    <th class="note">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['tanggal'] }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['alasan'] }}</td>
                        <td>{{ $row['catatan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Tidak ada data izin.</p>
    @endforelse
</body>
</html>

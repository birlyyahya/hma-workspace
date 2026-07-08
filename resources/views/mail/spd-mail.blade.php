<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Notifikasi SPD</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size:14px; color:#000; line-height:1.6;">

    <p>Yth. {{ $user->name }},</p>

    <p>
        Dengan ini kami informasikan bahwa <strong>Surat Perjalanan Dinas (SPD)</strong> Anda telah
        <strong>disetujui</strong>.
    </p>

    <p>Berikut detail perjalanan dinas:</p>

    <table cellpadding="4" cellspacing="0">
        <tr>
            <td width="150">Tujuan</td>
            <td>: {{ strip_tags($spd['destination'] ?? '-') }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{ strip_tags($spd['date'] ?? '-') }}</td>
        </tr>
    </table>

    <p>
        Mohon untuk mempersiapkan perjalanan dinas dengan baik serta melengkapi
        seluruh administrasi yang diperlukan.
    </p>

    <p>
        Demikian informasi ini kami sampaikan. Atas perhatian dan kerjasamanya,
        kami ucapkan terima kasih.
    </p>

    <br>

    <p>
        Hormat kami,<br>
        <strong>PT Hanatekindo Mulia Abadi</strong>
    </p>

    <hr>

    <p style="font-size:12px; color:#555;">
        Email ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.
    </p>

</body>
</html>

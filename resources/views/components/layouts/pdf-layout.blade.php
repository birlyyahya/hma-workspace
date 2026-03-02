<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Izin</title>
    <style>
        @page {
            size: A4;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: "Times New Roman", serif;
            color: #111;
        }
        body {
            background: radial-gradient(circle at top, #2f3035, #1e1f23);
            padding: 28px;
            font-size: 16px;
            line-height: 1.3;
        }

        .container {
            max-width: auto;
            height: 100%;
            max-height: 1060px;
            margin: 0 auto;
            background: #fff;
            border: 3px solid #111;
            padding: 0px 32px;
            border-radius: 5px;
            page-break-inside: avoid;
        }

        .header-table,
        .form-table {
            width: 100%;
            margin-top: 32px;
            border-collapse: collapse;
            table-layout: auto;
        }

        .header-table {
            border: 2px solid #111;
        }

        .header-table td {
            border-right: 2px solid #111;
            text-align: center;
            vertical-align: middle;
            padding: 10px;
        }

        .header-table td:last-child {
            border-right: 0;
        }

        .logo-cell {
            width: 160px;
        }

        .logo {
            width: 120px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .title {
            font-weight: 700;
            font-size: 21px;
            line-height: 1.2;
        }

        .content {
            padding: 20px 6px 0;
            min-height: 430px;
        }

        .form-table {
            font-size: 18px;
            line-height: 1.3;
        }

        .form-table td {
            vertical-align: top;
        }

        .intro-text {
            padding-bottom: 8px;
        }

        .label {
            width: 165px;
            white-space: nowrap;
            padding: 2px 4px 2px 0;
        }

        .colon {
            width: 14px;
            text-align: left;
            padding: 2px 4px 2px 0;
        }

        .value {
            border-bottom: 2px solid #111;
            padding: 0 2px 2px;
            word-break: break-word;
        }

        .multi {
            min-height: 34px;
        }

        .spacer {
            height: 38px;
        }

        .signature-section {
            padding: 120px 6px 0;
        }

        .signature-date {
            font-size: 19px;
            margin: 0 0 12px;
        }

        .signature-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            table-layout: fixed;
            margin: 0 -8px;
        }

        .signature-box {
            width: 32%;
            border: 2px solid #111;
            text-align: center;
            vertical-align: top;
        }

        .signature-title {
            min-height: 74px;
            font-size: 17px;
            line-height: 1.2;
            border-bottom: 2px solid #111;
            padding: 8px 6px;
        }

        .signature-image {
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
        }

        .signature-image img {
            max-width: 95%;
            max-height: 96px;
            object-fit: contain;
        }

        .signature-footer {
            font-size: 15px;
            line-height: 1.2;
            border-top: 2px solid #111;
            padding: 6px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .container {
                max-width: 100%;
                border-radius: 0;
                border-width: 2px;
                min-height: calc(297mm - 28mm);
                padding: 24px;
            }

            .content {
                min-height: 145mm;
            }
        }
    </style>
</head>
<body>
{{ $slot }}
</body>
</html>

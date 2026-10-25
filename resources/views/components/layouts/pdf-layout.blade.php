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
            padding: 0 12px;
            page-break-inside: avoid;
        }

        .header-table,
        .form-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            border: 2px solid #111;
            table-layout: fixed;
        }

        .header-table td {
            border-right: 2px solid #111;
            text-align: center;
            vertical-align: middle;
            height: 0px;
            padding: 8px;
        }

        .header-table td:last-child {
            border-right: 0;
        }

        .logo-cell {
            width: 18%;
            padding: 8px;
        }

        .logo {
            width: 116px;
            max-width: 100%;
            max-height: 108px;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .title-cell {
            width: 82%;
            padding: 12px 18px;
        }

        .title {
            font-family: Arial, sans-serif;
            font-weight: 700;
            font-size: 20px;
            line-height: 1.2;
        }

        .company-title {
            margin-top: 8px;
        }

        .content {
            border-right: 2px solid #111;
            border-bottom: 2px solid #111;
            border-left: 2px solid #111;
            padding: 30px 30px 30px;
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

        .leave-reason-cell {
            padding: 0 0 2px;
        }

        .leave-reason-title {
            padding-bottom: 8px;
        }

        .leave-reason-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .leave-reason-option {
            width: 33.33%;
            padding: 8px;
            vertical-align: bottom;
            white-space: nowrap;
        }


        .checkbox {
            display: inline-block;
            width: 15px;
            height: 15px;
            margin-right: 8px;
            border: 2px solid #111;
            text-align: center;
            line-height: 15px;
            vertical-align: middle;
            box-sizing: border-box;
        }

        /* Check mark menggunakan CSS border */
        .checkbox-mark {
            display: inline-block;
            width: 4px;
            height: 18px;
            border: solid #111;
            border-width: 0 2.5px 2.5px 0;
            transform: rotate(45deg);
            margin-top: -9px;
            margin-right: -8px;
        }


        /* check mark */
        .checkbox-mark {

        }

        .reason-text{
            line-height: 15px;
            display: inline-block;
        }

        .multi {
            min-height: 34px;
        }

        .spacer {
            height: 38px;
        }

        .signature-section {
            padding: 50px 0px 0;
        }

        .signature-date {
            font-size: 19px;
            margin: 0 0 12px;
        }

        .signature-table {
            width: 105%;
            border-collapse: separate;
            border-spacing: 10px 0;
            table-layout: fixed;
            margin: 0 -15px;
        }

        .signature-box {
            width: 33%;
            border: 2px solid #111;
            text-align: center;
            vertical-align: top;
        }

        .signature-title {
            font-size: 17px;
            line-height: 1.2;
            /* border-bottom: 2px solid #111; */
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
                border: 0;
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

@php
    $voucher = \App\Models\Voucher::findOrFail($id);
    $setting = \App\Models\AppSetting::first();
    $account = $voucher->account;
    
    // Process logo
    $logoPath = public_path('assets/images/auth/bg-img-2.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $mime = mime_content_type($logoPath) ?: 'image/png';
        $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    $amountInWords = \App\Services\ArabicAmountToWords::translate($voucher->amount, $voucher->currency);
    $voucherTypeLabel = $voucher->type == 'receipt' ? 'إيصال استلام نقدية' : 'إيصال صرف نقدية';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $voucherTypeLabel }} #{{ $voucher->id }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: 'Cairo', sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        .page-wrapper {
            width: 210mm;
            height: 297mm;
            display: flex;
            flex-direction: column;
        }

        .voucher-wrapper {
            height: 50%;
            width: 100%;
            padding: 15mm;
            box-sizing: border-box;
            position: relative;
            border-bottom: 1px dashed #ccc; /* Cut line */
        }

        .voucher-wrapper:last-child {
            border-bottom: none;
        }

        .voucher-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5mm;
        }

        .company-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-img {
            height: 40px;
            width: auto;
        }

        .company-name {
            font-weight: 700;
            font-size: 16px;
        }

        .date-section {
            font-size: 14px;
        }

        .title-section {
            text-align: center;
            margin-bottom: 5mm;
        }

        .title-section h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            border-bottom: 2px solid #000;
            display: inline-block;
            padding-bottom: 2px;
        }

        .voucher-no {
            display: block;
            margin-top: 5px;
            font-size: 14px;
            font-weight: 600;
        }

        .content-body {
            margin-top: 5mm;
        }

        .input-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 10mm;
            width: 100%;
        }

        .label {
            font-weight: 600;
            white-space: nowrap;
            margin-left: 10px;
            font-size: 15px;
        }

        .value-line {
            flex-grow: 1;
            border-bottom: 1px solid #999;
            padding: 0 10px;
            font-size: 16px;
            font-weight: 600;
            min-height: 25px;
        }

        .footer-section {
            margin-top: 15mm;
            display: flex;
            justify-content: space-between;
            padding: 0 20mm;
        }

        .sig-box {
            text-align: center;
            width: 150px;
        }

        .sig-label {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 15px;
            display: block;
        }

        .sig-line {
            border-top: 1px solid #000;
            width: 100%;
            height: 1px;
        }

        .print-btn {
            position: fixed;
            top: 10px;
            left: 10px;
            background: #000;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 1000;
        }

        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">طباعة</button>

    <div class="page-wrapper">
        <div class="voucher-wrapper">
            <div class="voucher-container">
                <div class="header-top">
                    <div class="company-section">
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
                        @endif
                        <span class="company-name">{{ $setting->company_name ?? 'اسم الشركة' }}</span>
                    </div>
                    <div class="date-section">
                        التاريخ: &nbsp;&nbsp; {{ date('Y / m / d', strtotime($voucher->date)) }}
                    </div>
                </div>

                <div class="title-section">
                    <h1>{{ $voucherTypeLabel }}</h1>
                    <div class="voucher-no">No. #{{ str_pad($voucher->id, 5, '0', STR_PAD_LEFT) }}</div>
                </div>

                <div class="content-body">
                    <div class="input-row">
                        <span class="label">استلمنا من السيد:</span>
                        <div class="value-line">{{ $account->name ?? '------------------------------------------------------------' }}</div>
                    </div>

                    <div class="input-row">
                        <span class="label">مبلغ وقدره:</span>
                        <div class="value-line" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>{{ $amountInWords }}</span>
                            <span style="border: 2px solid #000; padding: 2px 10px; font-weight: 800; background: #eee;">
                                {{ number_format($voucher->amount, $voucher->currency === 'USD' ? 2 : 0) }} {{ $voucher->currency }}
                            </span>
                        </div>
                    </div>

                    <div class="input-row">
                        <span class="label">وذلك قيمة:</span>
                        <div class="value-line">{{ $voucher->notes ?: '------------------------------------------------------------' }}</div>
                    </div>
                </div>

                <div class="footer-section">
                    <div class="sig-box">
                        <span class="sig-label">الختم</span>
                    </div>
                    <div class="sig-box">
                        <span class="sig-label">المستلم</span>
                        <div class="sig-line"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Bottom half remains empty -->
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>

</html>
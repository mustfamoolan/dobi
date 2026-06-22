@php
    $voucher = \App\Models\Voucher::findOrFail($id);
    $setting = \App\Models\AppSetting::first();
    $account = $voucher->account;
    
    // Process background
    $bgImagePath = public_path('assets/images/receipt.png');
    $bgImageBase64 = '';
    if (file_exists($bgImagePath)) {
        $mime = mime_content_type($bgImagePath) ?: 'image/png';
        $bgImageBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($bgImagePath));
    }

    $amountInWords = \App\Services\ArabicAmountToWords::translate($voucher->amount, $voucher->currency);
    $voucherTypeLabel = $voucher->type == 'receipt' ? 'إيصال استلام نقدية' : 'إيصال صرف نقدية';

    $customerBalanceInfo = null;
    if ($voucher->account_type === 'customer' && $account) {
        $ledgerEntry = \App\Models\CustomerLedger::where('ref_type', 'voucher')
            ->where('ref_id', $voucher->id)
            ->first();
            
        if ($ledgerEntry) {
            $remaining = $ledgerEntry->balance;
            $paid = $voucher->amount;
            $total = ($voucher->type === 'receipt') ? $remaining + $paid : $remaining - $paid;
            
            $customerBalanceInfo = [
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining
            ];
        }
    }
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
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
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
            background-image: url('{{ $bgImageBase64 }}') !important;
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: center;
            border-bottom: 1px dashed #ccc; /* Cut line */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .voucher-wrapper:last-child {
            border-bottom: none;
        }

        .voucher-container {
            width: 100%;
            height: 100%;
            position: relative;
            padding-top: 38mm; /* Pushes content down slightly more */
            box-sizing: border-box;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5mm;
        }

        .date-section {
            font-size: 14px;
            font-weight: bold;
        }

        .voucher-no {
            font-size: 14px;
            font-weight: bold;
        }

        .content-body {
            margin-top: 2mm;
        }

        .input-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 5mm;
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
            margin-top: 8mm;
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
            .voucher-wrapper {
                background-image: url('{{ $bgImageBase64 }}') !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">طباعة</button>

    <div class="page-wrapper">
        <div class="voucher-wrapper">
            <div class="voucher-container">
                <div class="header-top" style="margin-bottom: 5mm;">
                    <div class="voucher-no">
                        No. #{{ str_pad($voucher->id, 5, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="date-section">
                        التاريخ: &nbsp;&nbsp; {{ date('Y / m / d', strtotime($voucher->date)) }}
                    </div>
                </div>

                <div class="content-body">
                    <div class="input-row">
                        <span class="label">{{ $voucher->type == 'receipt' ? 'استلمنا من السيد:' : 'يصرف للسيد:' }}</span>
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

                    @if($customerBalanceInfo)
                        <div style="margin-top: 10px; border: 1px solid #000; border-radius: 4px; overflow: hidden;">
                            <table style="width: 100%; text-align: center; border-collapse: collapse; font-size: 14px;">
                                <tr style="background: #f5f5f5;">
                                    <th style="border: 1px solid #000; padding: 4px;">الرصيد الكلي</th>
                                    <th style="border: 1px solid #000; padding: 4px;">المبلغ المدفوع</th>
                                    <th style="border: 1px solid #000; padding: 4px;">الرصيد المتبقي</th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 6px; font-weight: 700;">{{ number_format($customerBalanceInfo['total'], 0) }} <small>{{ $voucher->currency }}</small></td>
                                    <td style="border: 1px solid #000; padding: 6px; font-weight: 700;">{{ number_format($customerBalanceInfo['paid'], 0) }} <small>{{ $voucher->currency }}</small></td>
                                    <td style="border: 1px solid #000; padding: 6px; font-weight: 700;">{{ number_format($customerBalanceInfo['remaining'], 0) }} <small>{{ $voucher->currency }}</small></td>
                                </tr>
                            </table>
                        </div>
                    @endif
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
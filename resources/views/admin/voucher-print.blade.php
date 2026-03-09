@php
    $voucher = \App\Models\Voucher::with(['customer', 'supplier', 'employee'])->find($id);
    $setting = \App\Models\AppSetting::first();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Voucher') }} #{{ $voucher->id }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .voucher-box {
            max-width: 700px;
            margin: auto;
            border: 2px solid #333;
            padding: 40px;
            border-radius: 8px;
            position: relative;
        }

        .voucher-box::after {
            content: "";
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            pointer-events: none;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .voucher-type {
            display: inline-block;
            background: #333;
            color: #fff;
            padding: 5px 20px;
            border-radius: 20px;
            margin-top: 10px;
            font-weight: bold;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .field {
            margin-bottom: 15px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 5px;
        }

        .label {
            font-weight: bold;
            color: #666;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }

        .value {
            font-size: 18px;
            color: #000;
        }

        .amount-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            margin: 30px 0;
            border: 1px solid #eee;
        }

        .amount-box {
            display: inline-block;
            border: 2px solid #333;
            padding: 10px 30px;
            font-size: 24px;
            font-weight: bold;
            margin-top: 10px;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }

        .signature-box {
            text-align: center;
            width: 200px;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 10px;
        }

        .print-btn {
            background: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                padding: 0;
            }

            .voucher-box {
                border: 2px solid #000;
            }
        }
    </style>
</head>

<body>
    <div class="voucher-box">
        <button class="print-btn" onclick="window.print()">{{ __('Print') }}</button>

        <div class="header">
            <h1>{{ $setting->company_name ?? 'AlWaseet Admin' }}</h1>
            <div class="voucher-type">
                {{ $voucher->type == 'receipt' ? __('Receipt Voucher') : __('Payment Voucher') }}
            </div>
        </div>

        <div class="info-grid">
            <div class="field">
                <span class="label">{{ __('Voucher ID') }}</span>
                <span class="value">#{{ $voucher->id }}</span>
            </div>
            <div class="field">
                <span class="label">{{ __('Date') }}</span>
                <span class="value">{{ $voucher->date }}</span>
            </div>
        </div>

        <div class="field">
            <span class="label">{{ $voucher->type == 'receipt' ? __('Received From') : __('Paid To') }}</span>
            <span class="value">
                @if($voucher->customer_id) {{ $voucher->customer->name }}
                @elseif($voucher->supplier_id) {{ $voucher->supplier->name }}
                @elseif($voucher->employee_id) {{ $voucher->employee->name }}
                @else {{ __('General') }} @endif
            </span>
        </div>

        <div class="field" style="margin-top: 20px;">
            <span class="label">{{ __('Description') }}</span>
            <span class="value">{{ $voucher->description }}</span>
        </div>

        <div class="amount-section">
            <span class="label">{{ __('Amount') }}</span>
            <div class="amount-box">
                {{ number_format($voucher->amount, $voucher->currency === 'USD' ? 2 : 0) }} {{ $voucher->currency }}
            </div>
            @if($voucher->currency != 'USD')
                <div style="margin-top: 10px; font-size: 12px; color: #777;">
                    1 USD = {{ number_format($voucher->exchange_rate, 0) }} {{ $voucher->currency }}
                </div>
            @endif
        </div>

        <div class="footer">
            <div class="signature-box">
                <div class="signature-line">{{ __('Accountant') }}</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">{{ __('Receiver') }}</div>
            </div>
        </div>
    </div>
</body>

</html>
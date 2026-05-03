<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف حساب تفصيلي - {{ $account->name }}</title>
    <style>
        /* EXCEL LIKE PRINT DESIGN */
        @page { size: A4 portrait; margin: 10mm; }
        body { 
            background-color: white; 
            margin: 0; 
            padding: 0; 
            font-family: 'Calibri', 'Arial', sans-serif; 
            direction: rtl;
            color: #000;
        }
        
        .print-container { width: 100%; margin: 0 auto; }
        
        /* EXCEL LIKE TABLE */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 10pt; 
            margin-bottom: 15px;
        }
        table th, table td { 
            border: 1px solid #000000; 
            padding: 4px 6px; 
            color: #000; 
            vertical-align: middle;
        }
        
        .table-data th, .table-data td {
            text-align: center;
        }
        
        .table-data thead th { 
            background-color: #e6e6e6; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
            font-weight: bold; 
            border-bottom: 2px solid #000000;
        }
        
        .text-end { text-align: left !important; }
        .text-center { text-align: center !important; }
        
        .table-info { background-color: #f2f2f2; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }
        .table-footer { background-color: #e6e6e6; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }
        
        /* HEADER LAYOUT */
        .excel-header-table { border: none; margin-bottom: 15px; }
        .excel-header-table td { border: none; padding: 0; }
        
        .meta-table th { background-color: #e6e6e6; font-weight: bold; width: 15%; text-align: right; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .meta-table td { width: 35%; text-align: right; font-weight: bold; }
        
        .badge { display: inline-block; padding: 2px 4px; border: 1px solid #000; border-radius: 3px; font-size: 8pt; margin-right: 5px; }
        .notes-text { font-size: 8pt; color: #333; display: block; margin-top: 3px; }

        @media print {
            .no-print { display: none !important; }
        }
        
        .print-btn-container {
            text-align: center;
            margin: 20px 0;
        }
        .print-btn {
            background-color: #32267d;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14pt;
            cursor: pointer;
            border-radius: 5px;
            font-family: inherit;
        }
    </style>
</head>
<body>
    <div class="print-container">
        
        <div class="print-btn-container no-print">
            <button class="print-btn" onclick="window.print()">🖨️ طباعة الكشف</button>
            <button class="print-btn" onclick="window.close()" style="background-color: #6c757d; margin-right: 10px;">إغلاق</button>
        </div>

        <table class="excel-header-table">
            <tr>
                <td style="text-align:right; width: 33%; vertical-align: top;">
                    <img height="40" src="{{ asset('assets/images/DOKKAN.png') }}" style="margin-bottom: 5px;"><br>
                    <strong style="font-size: 12pt;">{{ config('app.name') }}</strong>
                </td>
                <td style="text-align:center; width: 33%; vertical-align: middle;">
                    <h2 style="margin:0; font-weight:bold; font-size: 16pt;">كشف حساب تفصيلي</h2>
                </td>
                <td style="text-align:left; width: 33%; vertical-align: top; font-size: 9pt;">
                    تاريخ الطباعة: {{ date('Y-m-d H:i') }}
                </td>
            </tr>
        </table>
        
        <table class="meta-table">
            <tr>
                <th>الحساب:</th>
                <td>{{ $account->name }}</td>
                <th>الفترة:</th>
                <td dir="ltr" style="text-align: left;">{{ $fromDate }} <span>&rarr;</span> {{ $toDate }}</td>
            </tr>
            <tr>
                <th>النوع:</th>
                <td>{{ $account->type == 'cash' ? 'نقدي (صندوق)' : 'بنكي' }}</td>
                <th>العملة:</th>
                <td dir="ltr" style="text-align: left;">{{ $account->currency }}</td>
            </tr>
        </table>

        <table class="table-data">
            <thead>
                <tr>
                    <th style="width: 12%;">التاريخ</th>
                    <th style="width: 40%;">البيان (الوصف)</th>
                    <th style="width: 16%;">وارد (+)</th>
                    <th style="width: 16%;">صادر (-)</th>
                    <th style="width: 16%;">الرصيد المتراكم</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-info">
                    <td colspan="2" style="text-align: right;">الرصيد الافتتاحي (المدور) قبل {{ $fromDate }}</td>
                    <td>-</td>
                    <td>-</td>
                    <td dir="ltr" style="text-align: left;">{{ number_format($previousBalance, 0) }}</td>
                </tr>
                
                @php $currentBalance = $previousBalance; @endphp
                @foreach($entries as $entry)
                    @php $currentBalance += ($entry->debit - $entry->credit); @endphp
                    <tr>
                        <td>{{ $entry->date }}</td>
                        <td style="text-align: right;">
                            {{ $entry->description }}
                            @if($entry->ref_id)
                                <span class="badge">#{{ $entry->ref_id }}</span>
                                @php $ref = $entry->reference; @endphp
                                @if($ref && !empty($ref->notes))
                                    <span class="notes-text">- {{ $ref->notes }}</span>
                                @endif
                            @endif
                        </td>
                        <td dir="ltr" style="text-align: left;">{{ $entry->debit > 0 ? number_format($entry->debit, 0) : '-' }}</td>
                        <td dir="ltr" style="text-align: left;">{{ $entry->credit > 0 ? number_format($entry->credit, 0) : '-' }}</td>
                        <td dir="ltr" style="text-align: left;"><strong>{{ number_format($currentBalance, 0) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="table-footer">
                    <th colspan="2" style="text-align: right;">الرصيد الختامي والإجماليات</th>
                    <th dir="ltr" style="text-align: left;">{{ number_format($entries->sum('debit'), 0) }}</th>
                    <th dir="ltr" style="text-align: left;">{{ number_format($entries->sum('credit'), 0) }}</th>
                    <th dir="ltr" style="text-align: left;">{{ number_format($currentBalance, 0) }} {{ $account->currency }}</th>
                </tr>
            </tfoot>
        </table>
        
        <div style="text-align: center; font-size: 9pt; margin-top: 20px; color: #555;">
            نهاية كشف الحساب. هذا المستند تم إنشاؤه آلياً.
        </div>
    </div>
    
    <script>
        // Auto print when opened
        window.onload = function() {
            // Optional: setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>

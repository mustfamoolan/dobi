@php
    $id = request('id');
    $fromDate = request('from_date', now()->startOfMonth()->format('Y-m-d'));
    $toDate = request('to_date', now()->format('Y-m-d'));
    $currency = request('currency', 'IQD');

    // Convert background image to base64 so html2canvas never needs an HTTP request
    $bgImagePath = public_path('assets/images/report.png');
    $bgImageBase64 = '';
    if (file_exists($bgImagePath)) {
        $mime = mime_content_type($bgImagePath) ?: 'image/png';
        $bgImageBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($bgImagePath));
    }

    $customer = \App\Models\Customer::findOrFail($id);

    // Get Ledger entries
    $query = \App\Models\CustomerLedger::where('customer_id', $id)
        ->where('currency', $currency)
        ->whereBetween('date', [$fromDate, $toDate])
        ->orderBy('date', 'asc')
        ->orderBy('id', 'asc');

    $entries = $query->get();

    // Calculate Opening Balance before the "from" date
    $previousBalance = \App\Models\CustomerLedger::where('customer_id', $id)
        ->where('currency', $currency)
        ->where('date', '<', $fromDate)
        ->selectRaw('SUM(debit) - SUM(credit) as balance')
        ->first()->balance ?? 0;

    $currencySymbol = ($currency === 'USD') ? '$' : 'د.ع';

    // Prepare data for JS
    $reportData = [
        'customer' => [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address ?? '',
        ],
        'period' => [
            'from' => $fromDate,
            'to' => $toDate,
            'currency' => $currency,
            'symbol' => $currencySymbol
        ],
        'previous_balance' => (float) $previousBalance,
        'entries' => $entries->map(function ($entry) {
            return [
                'date' => $entry->date,
                'description' => $entry->description . ($entry->ref_id ? " #{$entry->ref_id}" : ""),
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
            ];
        })
    ];
@endphp
<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8" />
    <title>{{ __('Statement') }} - {{ $customer->name }}</title>

    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        :root {
            /* =============================================
               منطقة المعايرة - CALIBRATION ZONE
               ============================================= */
            --print-top: 68mm;
            --print-left: 12mm;
            --print-width: 186mm;
            --print-height: 185mm;
            --row-height: 8.5mm;
            --font-size-base: 9.5pt;
        }

        @page {
            size: A4;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Tahoma', 'Arial', sans-serif;
            font-size: var(--font-size-base);
            background: #f0f0f0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page-container {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background-color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-image: url('{{ $bgImageBase64 }}') !important;
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: center;
        }

        @media print {
            body {
                background: none;
            }

            .no-print {
                display: none !important;
            }

            .page-container {
                box-shadow: none;
                margin: 0;
                background-image: url('{{ $bgImageBase64 }}') !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        .print-area {
            position: absolute;
            top: var(--print-top);
            left: var(--print-left);
            width: var(--print-width);
            height: var(--print-height);
            display: flex;
            flex-direction: column;
        }

        /* Info Table */
        .info-table {
            width: 100%;
            margin-bottom: 4mm;
            color: #32267d;
            border: 1px solid #b0a8d8;
            background: rgba(243, 241, 251, 0.8);
            border-radius: 2mm;
            border-collapse: separate;
            border-spacing: 0;
            letter-spacing: normal !important;
        }

        .info-table td {
            border: none !important;
            padding: 2mm 3mm !important;
            vertical-align: middle;
            letter-spacing: normal !important;
        }

        .info-label {
            color: #7a6fb0;
            font-size: 8.5pt;
            margin-left: 1mm;
            white-space: nowrap;
        }

        .info-value {
            font-weight: 800;
            color: #32267d;
            white-space: nowrap;
        }

        /* Table */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table thead th {
            border: 1px solid #32267d;
            background-color: #32267d;
            color: #ffffff;
            padding: 2mm 1mm;
            text-align: center;
            font-size: 10pt;
        }

        .report-table tbody td {
            border: 1px solid #b0a8d8;
            padding: 1mm 2mm;
            height: var(--row-height);
            vertical-align: middle;
            font-size: 9pt;
            color: #32267d;
            word-break: break-word;
        }

        .row-even {
            background-color: #f3f1fb;
        }

        .text-end {
            text-align: left;
        }

        /* For currency numbers in RTL, often left-aligned looks better or centered */
        .text-center {
            text-align: center;
        }

        .debit-cell {
            color: #d32f2f;
            font-weight: bold;
        }

        .credit-cell {
            color: #388e3c;
            font-weight: bold;
        }

        .summary-box {
            margin-top: 5mm;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border: 1px solid #32267d;
            background: #ffffff;
        }

        .summary-cell {
            border-left: 1px solid #b0a8d8;
            padding: 2mm;
            text-align: center;
        }

        .summary-label {
            font-weight: bold;
            font-size: 8pt;
            color: #7a6fb0;
            display: block;
            margin-bottom: 1mm;
        }

        .summary-value {
            font-weight: 800;
            font-size: 10pt;
            color: #32267d;
        }

        /* Controls */
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            gap: 10px;
        }

        .btn {
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 6px;
            font-weight: bold;
            color: white;
        }

        .btn-print {
            background: #32267d;
        }

        .btn-download {
            background: #1a7d4e;
        }
    </style>
</head>

<body>
    <div class="controls no-print">
        <button class="btn btn-print" onclick="window.print()">🖨️ {{ __('Print') }}</button>
        <button class="btn btn-download" onclick="downloadAsPDF()">⬇️ {{ __('Download PDF') }}</button>
    </div>

    <div id="report-pages"></div>

    <template id="page-template">
        <div class="page-container">
            <div class="print-area">
                <div style="text-align: center; margin-bottom: 3mm; font-size: 14pt; font-weight: bold; color: #32267d;">
                    كشف حساب
                </div>
                <table class="info-table">
                    <tr>
                        <td style="width: 45%; text-align: right;">
                            <span class="info-label">العميل:</span>
                            <span class="info-value data-name"></span>
                        </td>
                        <td style="width: 35%; text-align: center;">
                            <span class="info-label">التاريخ:</span>
                            <span class="info-value data-period"></span>
                        </td>
                        <td style="width: 20%; text-align: left;">
                            <span class="info-label">العملة:</span>
                            <span class="info-value data-currency"></span>
                        </td>
                    </tr>
                </table>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 28mm; white-space: nowrap;">{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th style="width: 30mm;">{{ __('Debit') }}</th>
                            <th style="width: 30mm;">{{ __('Credit') }}</th>
                            <th style="width: 32mm;">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="data-items"></tbody>
                </table>

                <div class="footer-summary" style="margin-top: auto; padding-bottom: 10mm;">
                    <div class="summary-box">
                        <div class="summary-cell"><span class="summary-label">إجمالي الديون (عليه)</span><span
                                class="summary-value data-total-debit"></span></div>
                        <div class="summary-cell"><span class="summary-label">إجمالي المسدد (له)</span><span
                                class="summary-value data-total-credit"></span></div>
                        <div class="summary-cell"><span class="summary-label">رصيد سابق</span><span
                                class="summary-value data-prev-bal"></span></div>
                        <div class="summary-cell" style="border-left: none;"><span class="summary-label">الرصيد النهائي (المتبقي)</span><span class="summary-value data-final-bal"></span></div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        const reportData = @json($reportData);

        function renderReport(data) {
            const container = document.getElementById('report-pages');
            const template = document.getElementById('page-template');
            const itemsPerPage = 15;

            let currentBalance = data.previous_balance;
            let totalDebit = 0;
            let totalCredit = 0;

            const totalPages = Math.ceil(data.entries.length / itemsPerPage) || 1;

            for (let i = 0; i < totalPages; i++) {
                const page = template.content.cloneNode(true);

                // Header Info
                page.querySelector('.data-name').textContent = data.customer.name;
                page.querySelector('.data-period').textContent = `${data.period.from} - ${data.period.to}`;
                page.querySelector('.data-currency').textContent = data.period.currency;

                const tbody = page.querySelector('.data-items');

                // Opening Balance Row (only on first page)
                if (i === 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td colspan="2" style="font-weight: bold; background: #eee;">رصيد سابق (ما قبل ${data.period.from})</td>
                        <td>-</td>
                        <td>-</td>
                        <td class="text-center" style="font-weight: bold;">${data.previous_balance.toLocaleString()} ${data.period.symbol}</td>
                    `;
                    tbody.appendChild(tr);
                }

                const slice = data.entries.slice(i * itemsPerPage, (i + 1) * itemsPerPage);
                slice.forEach((entry, idx) => {
                    currentBalance += (entry.debit - entry.credit);
                    totalDebit += entry.debit;
                    totalCredit += entry.credit;

                    const tr = document.createElement('tr');
                    if (idx % 2 === 0) tr.className = 'row-even';
                    tr.innerHTML = `
                        <td class="text-center" style="white-space: nowrap;">${entry.date}</td>
                        <td>${entry.description}</td>
                        <td class="text-center debit-cell">${entry.debit > 0 ? entry.debit.toLocaleString() : '-'}</td>
                        <td class="text-center credit-cell">${entry.credit > 0 ? entry.credit.toLocaleString() : '-'}</td>
                        <td class="text-center" style="font-weight: bold;">${currentBalance.toLocaleString()}</td>
                    `;
                    tbody.appendChild(tr);
                });

                // Pad empty rows
                const remaining = itemsPerPage - slice.length;
                for (let p = 0; p < remaining; p++) {
                    const tr = document.createElement('tr');
                    if ((slice.length + p) % 2 === 0) tr.className = 'row-even';
                    tr.innerHTML = `<td>&nbsp;</td><td></td><td></td><td></td><td></td>`;
                    tbody.appendChild(tr);
                }

                // Summary (only on last page)
                if (i === totalPages - 1) {
                    page.querySelector('.data-total-debit').textContent = totalDebit.toLocaleString() + ' ' + data.period.symbol;
                    page.querySelector('.data-total-credit').textContent = totalCredit.toLocaleString() + ' ' + data.period.symbol;
                    page.querySelector('.data-prev-bal').textContent = data.previous_balance.toLocaleString() + ' ' + data.period.symbol;
                    page.querySelector('.data-final-bal').textContent = currentBalance.toLocaleString() + ' ' + data.period.symbol;
                } else {
                    page.querySelector('.footer-summary').style.visibility = 'hidden';
                }

                container.appendChild(page);
            }
        }

        async function downloadAsPDF() {
            const { jsPDF } = window.jspdf;
            const pages = document.querySelectorAll('.page-container');
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

            try {
                for (let i = 0; i < pages.length; i++) {
                    const imgData = await htmlToImage.toPng(pages[i], {
                        pixelRatio: 2,
                        style: {
                            margin: '0'
                        }
                    });
                    if (i > 0) pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, 0, 210, 297, undefined, 'FAST');
                }
                pdf.save(`Statement_${reportData.customer.name}_${reportData.period.from}.pdf`);
            } catch (error) {
                console.error('PDF Generation failed:', error);
                alert('حدث خطأ أثناء تحميل كشف الحساب كـ PDF.');
            }
        }

        window.addEventListener('load', () => {
            renderReport(reportData);
            const params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1') {
                setTimeout(() => window.print(), 800);
            } else if (params.get('autodownload') === '1') {
                setTimeout(() => downloadAsPDF(), 1000);
            }
        });
    </script>
</body>

</html>
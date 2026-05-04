@php
    // Convert background image to base64 so html2canvas never needs an HTTP request
    // This is the root fix for blank PDF on first download
    $bgImagePath = public_path('assets/images/invois.png');
    $bgImageBase64 = '';
    if (file_exists($bgImagePath)) {
        $mime = mime_content_type($bgImagePath) ?: 'image/png';
        $bgImageBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($bgImagePath));
    }
    $model = $type == 'sale' ? \App\Models\Sale::with(['customer', 'items.product'])->find($id) : \App\Models\Purchase::with(['supplier', 'items.product'])->find($id);
    $setting = \App\Models\AppSetting::first();

    $typeLabel = $type == 'sale' ? ($model->type == 'invoice' ? 'Invoice' : ($model->type == 'quotation' ? 'Quotation' : 'Proforma')) : 'Purchase Invoice';

    $previousBalance = 0;
    if ($type == 'sale' && $model->customer) {
        $previousBalance = $model->customer->getBalanceBeforeSale($model->id, $model->currency);
    }

    $idPrefix = $type == 'sale' ? ($model->type == 'invoice' ? 'INV-' : ($model->type == 'quotation' ? 'QUO-' : 'PRO-')) : 'PUR-';
    $formattedId = $idPrefix . str_pad($model->id, 3, '0', STR_PAD_LEFT);
    // Transform model to JSON for the JS printer
    $invoiceData = [
        'id' => $formattedId,
        'type_raw' => $model->type ?? 'invoice',
        'type_label' => $typeLabel,
        'date' => $model->date,
        'customer' => [
            'name' => $model->customer->name ?? $model->supplier->name ?? '',
            'phone' => $model->customer->phone ?? $model->supplier->phone ?? '',
            'address' => $model->customer->address ?? $model->supplier->address ?? '',
        ],
        'currency' => $model->currency,
        'items' => $model->items->map(function ($item, $index) {
            return [
                'no' => $index + 1,
                'name' => $item->product->name,
                'qty' => ($item->qty == (int)$item->qty) ? (int)$item->qty : (float)$item->qty,
                'price' => $item->price ?? $item->cost,
                'total' => $item->subtotal
            ];
        }),
        'totals' => [
            'subtotal' => $model->total,
            'discount' => $model->discount ?? 0,
            'extra' => $model->tax ?? 0,
            'net' => $model->grand_total,
            'paid' => $type === 'sale' ? $model->paidAmount() : (($model->payment_status === 'paid') ? $model->grand_total : 0),
            'previous' => $previousBalance,
            'total_balance' => $previousBalance + ($type === 'sale' ? $model->remainingAmount() : (($model->payment_status === 'paid') ? 0 : $model->grand_total)),
            'remaining' => $type === 'sale' ? $model->remainingAmount() : (($model->payment_status === 'paid') ? 0 : $model->grand_total),
            'words' => \App\Services\ArabicAmountToWords::translate($model->grand_total, $model->currency),
            'notes' => $model->notes
        ]
    ];
@endphp
<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8" />
    <title>Invoice #{{ $formattedId }}</title>

    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        :root {
            /* =============================================
               منطقة المعايرة الرئيسية - MAIN CALIBRATION ZONE
               عدّل القيم هنا لضبط موقع منطقة الطباعة فوق الخلفية
               ============================================= */

            /* المسافة من أعلى الصفحة — top of print area from top of page */
            --print-top: 90mm;

            /* المسافة من يسار الصفحة — left offset of print area */
            --print-left: 12mm;

            /* عرض منطقة الطباعة — width of the print area */
            --print-width: 186mm;

            /* ارتفاع منطقة الطباعة — total height of the print area */
            --print-height: 185mm;

            /* ارتفاع كل صف في الجدول — height of each table row */
            --row-height: 8mm;

            /* حجم الخط الأساسي — base font size */
            --font-size-base: 9pt;

            /* حجم خط العناوين — header font size */
            --font-size-header: 10pt;
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

            /* Background image embedded as Base64 — no HTTP request needed */
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

            /* === Print Mode (no background) === */
            .page-container {
                box-shadow: none;
                margin: 0;
                background-image: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* === Download Mode (WITH background image) === */
            body.download-mode .page-container {
                background-image: url('{{ $bgImageBase64 }}') !important;
                background-size: 100% 100% !important;
                background-repeat: no-repeat !important;
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
            /* border: 1px dashed #ccc; /* Only for calibration preview */
            display: flex;
            flex-direction: column;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1.5fr; /* No, Date, Customer (in RTL) */
            gap: 2mm;
            margin-bottom: 2mm;
            color: #32267d;
            border: 1px solid #b0a8d8;
            background: #f3f1fb;
            padding: 1.5mm 3mm;
            border-radius: 1.5mm;
            align-items: stretch; 
            direction: rtl; 
        }

        .type-header-inline {
            font-size: 9pt;
            font-weight: bold;
            color: #32267d;
            border-bottom: 1px solid #32267d;
            margin-bottom: 1.5mm;
            padding-bottom: 0.5mm;
            display: inline-block;
            white-space: nowrap;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5mm;
            text-align: left;
            justify-content: flex-end; /* Align contents to bottom */
            height: 100%;
        }

        .info-item.center-cell {
            text-align: center;
            align-items: center;
        }

        .info-item.customer-cell {
            text-align: right; 
            align-items: flex-end;
            direction: rtl;
        }

        .info-item label {
            color: #7a6fb0;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .info-item span {
            color: #32267d;
            font-weight: 800;
            font-size: 9.5pt;
        }

        /* Table Styling */
        .table-container-pre {
            position: absolute;
            top: var(--print-top);
            left: var(--print-left);
            width: var(--print-width);
            height: var(--print-height);
            overflow: hidden;
            /* Prevent spillover */
            display: flex;
            flex-direction: column;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* مهم: لا تغيّر / Required for fixed columns */

            /* ↓ مسافة من أعلى منطقة الطباعة إلى بداية الجدول
               MOVE TABLE DOWN: زد هذا الرقم لتنزيل الجدول */
            margin-top: 1mm;
        }

        .invoice-table thead th {
            border: 1px solid #32267d;

            /* ↓ لون خلفية رأس الجدول — table header background */
            background-color: #32267d;
            color: #ffffff;

            padding: 1.5mm 1mm;
            text-align: center;
            font-size: 9pt;
        }

        .invoice-table tbody td {
            border-right: 1px solid #b0a8d8;
            border-left: 1px solid #b0a8d8;

            /* ↓ الحشوة الداخلية لكل خلية — inner cell padding (top/bottom x left/right) */
            padding: 0.5mm 1.5mm;

            /* ↓ ارتفاع الصف — row height (تغيّر من :root أعلاه) */
            height: var(--row-height);
            vertical-align: middle;

            /* ↓ حجم الخط — font size */
            font-size: 9pt;
            color: #32267d;
            overflow: hidden;
            /* Allow text to wrap for Arabic names */
            white-space: normal;
            word-break: break-word;
        }

        .invoice-table tbody tr:last-child td {
            border-bottom: 1px solid #b0a8d8;
        }

        /* Alternating row color */
        .invoice-table tbody tr:nth-child(even) td {
            background-color: #f3f1fb;
        }

        /* =============================================
           عرض الأعمدة — COLUMN WIDTHS (قابلة للتعديل)
           المجموع يجب أن يساوي تقريباً: --print-width ناقص padding
           ============================================= */
        .col-no {
            width: 12mm;
            text-align: center;
        }

        /* رقم التسلسل */
        .col-qty {
            width: 18mm;
            text-align: center;
        }

        /* الكمية */
        .col-price {
            width: 28mm;
            text-align: center;
        }

        /* السعر */
        .col-total {
            width: 32mm;
            text-align: center;
        }

        /* الإجمالي */
        .col-item {
            /* وصف الصنف - يأخذ الباقي تلقائياً */
            width: auto;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Summary Grid (Horizontal) — بلوك الأرصدة */
        .summary-grid {
            margin-top: 5mm;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1.2fr;
            border: 1px solid #32267d;
            font-size: 8.5pt;
            direction: rtl;
        }

        .summary-cell {
            border-left: 1px solid #b0a8d8;
            padding: 1.5mm 1mm;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1mm;
            justify-content: center;
            align-items: center;
        }

        .summary-cell:last-child {
            border-left: none;
        }

        .summary-cell.highlight-cell {
            background: #e3e8f8;
        }

        .summary-cell.highlight-cell .summary-value {
            font-size: 11pt;
        }

        .summary-label-row {
            display: flex;
            align-items: center;
            gap: 1.5mm;
            color: #32267d;
            border-bottom: 0.5px solid #b0a8d8;
            padding-bottom: 1mm;
            width: 100%;
            justify-content: center;
        }

        .summary-label-row svg {
            width: 3.5mm;
            height: 3.5mm;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .summary-label {
            font-weight: bold;
            color: #32267d;
        }

        .summary-value {
            font-weight: 800;
            color: #32267d;
            font-size: 9.5pt;
        }

        .total-in-words {
            grid-column: span 5; /* updated for 5 columns */
            padding: 2mm;
            text-align: center;
            font-weight: bold;
            background: #f3f1fb;
            border-top: 1px solid #b0a8d8;
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
            flex-direction: column;
            gap: 10px;
            width: 250px;
        }

        .btn {
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            font-size: 10pt;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.85;
        }

        /* Print button - blue */
        .btn-print {
            background: #32267d;
            color: white;
        }

        /* Download button - green */
        .btn-download {
            background: #1a7d4e;
            color: white;
        }

        /* Example button - gray */
        .btn-example {
            background: #6c757d;
            color: white;
        }

        .calibration-msg {
            font-size: 8.5pt;
            color: #666;
            margin-top: 5px;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>

<body>

    <div class="controls no-print">
        <button class="btn btn-print" onclick="printOnly()">
            🖨️ Print (بدون خلفية)
        </button>
        <button class="btn btn-download" onclick="downloadWithBg()">
            ⬇️ Download PDF (مع الخلفية)
        </button>
        <button class="btn btn-example" onclick="loadExampleData()">
            Load Example
        </button>
        <div class="calibration-msg">
            <b>Calibration:</b> Edit CSS :root variables to adjust mm positions.
        </div>
    </div>

    <div id="invoice-pages">
        <!-- Pages will be injected here if multi-page is needed -->
    </div>

    <template id="page-template">
        <div class="page-container">
            <div class="table-container-pre">
                <!-- Info Grid (Single row - 3 columns) -->
                <div class="info-grid" style="direction: ltr !important; display: flex !important; justify-content: space-between !important; gap: 2mm !important;">
                    <div class="info-item">
                        <div class="type-header-inline">
                            <span class="data-type-label"></span> | <span class="data-type-ar"></span>
                        </div>
                        <label>Invoice No:</label>
                        <span class="data-no"></span>
                    </div>
                    <div class="info-item center-cell">
                        <label>Date:</label>
                        <span class="data-date"></span>
                    </div>
                    <div class="info-item customer-cell">
                        <label>Customer:</label>
                        <span class="data-customer"></span>
                    </div>
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-item">Item Description</th>
                            <th class="col-qty">Qty</th>
                            <th class="col-price">Price</th>
                            <th class="col-total">Total</th>
                        </tr>
                    </thead>
                    <tbody class="data-items">
                        <!-- Items injected here -->
                    </tbody>
                </table>

                <!-- Notes Section -->
                <div class="notes-container"
                    style="display: none; margin-top: 4mm; border: 1px solid #32267d; border-radius: 1mm; padding: 2mm; background: #f3f1fb; font-size: 9pt;">
                    <div style="font-weight: bold; color: #32267d; margin-bottom: 1mm;">الملاحظات / Notes:</div>
                    <div class="data-notes" style="color: #32267d; white-space: pre-wrap;"></div>
                </div>

                <div class="summary-grid">
                    <div class="summary-cell">
                        <div class="summary-label-row">
                            <span class="summary-label">الرصيد الحالي</span>
                        </div>
                        <span class="summary-value data-total-balance">0</span>
                    </div>
                    <div class="summary-cell">
                        <div class="summary-label-row">
                            <span class="summary-label">الرصيد السابق</span>
                        </div>
                        <span class="summary-value data-previous">---</span>
                    </div>
                    <div class="summary-cell">
                        <div class="summary-label-row">
                            <span class="summary-label">المبلغ الواصل</span>
                        </div>
                        <span class="summary-value data-paid">0</span>
                    </div>
                    <div class="summary-cell">
                        <div class="summary-label-row">
                            <span class="summary-label">الخصم</span>
                        </div>
                        <span class="summary-value data-discount">0</span>
                    </div>
                    <div class="summary-cell highlight-cell">
                        <div class="summary-label-row">
                            <span class="summary-label">المبلغ الإجمالي</span>
                        </div>
                        <span class="summary-value data-net">0</span>
                    </div>
                    <div class="total-in-words data-words"></div>
                </div>
            </div>
        </div>
    </template>

    <script shadow>
        function printInvoice(data) {
            const container = document.getElementById('invoice-pages');
            const template = document.getElementById('page-template');
            container.innerHTML = '';

            const currencySymbol = data.currency === 'USD' ? '$' : 'د.ع';
            const itemsPerPage = 12; // Adjusted based on row height vs print-height
            const totalPages = Math.ceil(data.items.length / itemsPerPage) || 1;

            for (let i = 0; i < totalPages; i++) {
                const page = template.content.cloneNode(true);
                const pageNode = page.querySelector('.page-container');

                // Fill Header
                page.querySelector('.data-no').textContent = data.id;
                page.querySelector('.data-date').textContent = data.date;
                page.querySelector('.data-customer').textContent = data.customer.name;
                
                // Dynamic Type Labels
                const typeLabels = {
                    'invoice': { en: 'INVOICE', ar: 'فاتورة' },
                    'quotation': { en: 'QUOTATION', ar: 'عرض سعر' },
                    'proforma': { en: 'PROFORMA', ar: 'فاتورة أولية' }
                };
                const currentType = data.type_raw || 'invoice';
                page.querySelector('.data-type-label').textContent = typeLabels[currentType]?.en || 'INVOICE';
                page.querySelector('.data-type-ar').textContent = typeLabels[currentType]?.ar || 'فاتورة';

                // Fill Items
                const tbody = page.querySelector('.data-items');
                const slice = data.items.slice(i * itemsPerPage, (i + 1) * itemsPerPage);

                slice.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="col-no">${item.no}</td>
                        <td class="col-item">${item.name}</td>
                        <td class="col-qty">${Number(item.qty) % 1 === 0 ? parseInt(item.qty) : Number(item.qty)}</td>
                        <td class="col-price">${Number(item.price).toLocaleString()} ${currencySymbol}</td>
                        <td class="col-total">${Number(item.total).toLocaleString()} ${currencySymbol}</td>
                    `;
                    tbody.appendChild(tr);
                });

                // Fill Notes (only on last page)
                if (i === totalPages - 1 && data.totals.notes) {
                    const notesContainer = page.querySelector('.notes-container');
                    notesContainer.style.display = 'block';
                    page.querySelector('.data-notes').textContent = data.totals.notes;
                }

                // Pad empty rows to keep footer at bottom of print-area
                for (let p = slice.length; p < itemsPerPage; p++) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td class="col-no">&nbsp;</td><td class="col-item"></td><td class="col-qty"></td><td class="col-price"></td><td class="col-total"></td>`;
                    tbody.appendChild(tr);
                }

                // Fill Footer (only on last page)
                if (i === totalPages - 1) {
                    page.querySelector('.data-words').textContent = data.totals.words;
                    page.querySelector('.data-net').textContent = Number(data.totals.net).toLocaleString() + ' ' + currencySymbol;
                    if (page.querySelector('.data-subtotal')) {
                        page.querySelector('.data-subtotal').textContent = Number(data.totals.subtotal).toLocaleString() + ' ' + currencySymbol;
                    }
                    page.querySelector('.data-discount').textContent = Number(data.totals.discount).toLocaleString() + ' ' + currencySymbol;
                    page.querySelector('.data-paid').textContent = Number(data.totals.paid).toLocaleString() + ' ' + currencySymbol;
                    page.querySelector('.data-previous').textContent = Number(data.totals.previous).toLocaleString() + ' ' + currencySymbol;
                    page.querySelector('.data-total-balance').textContent = Number(data.totals.total_balance).toLocaleString() + ' ' + currencySymbol;
                } else {
                    page.querySelector('.summary-grid').style.visibility = 'hidden';
                }

                container.appendChild(page);
            }
        }

        // Example Data Function
        function loadExampleData() {
            const exampleData = {
                id: "EX-2024-001",
                date: new Date().toLocaleDateString(),
                customer: {
                    name: "Ali Ahmed Hassan",
                    phone: "+964 770 123 4567",
                    address: "Baghdad, Karrada St."
                },
                items: Array.from({ length: 20 }, (_, i) => ({
                    no: i + 1,
                    name: "Spare Part Item Description #" + (i + 1),
                    qty: Math.floor(Math.random() * 10) + 1,
                    price: 25000,
                    total: 25000 * (Math.floor(Math.random() * 10) + 1)
                })),
                totals: {
                    subtotal: 500000,
                    discount: 50000,
                    net: 450000,
                    paid: 450000,
                    remaining: 0,
                    words: "أربعمائة وخمسون ألف دينار عراقي لا غير"
                }
            };
            printInvoice(exampleData);
        }

        // Initial load with PHP data
        document.addEventListener('DOMContentLoaded', () => {
            const phpData = @json($invoiceData);
            printInvoice(phpData);
        });

        // === PRINT: without background image ===
        function printOnly() {
            document.body.classList.remove('download-mode');
            window.print();
        }

        // === DOWNLOAD: with background image ===
        // Adds .download-mode to body so @media print shows the background
        // Then tells user to "Save as PDF" in the print dialog
        function downloadWithBg() {
            document.body.classList.add('download-mode');
            setTimeout(() => {
                window.print();
                // Remove the class after printing dialog closes
                setTimeout(() => {
                    document.body.classList.remove('download-mode');
                }, 1000);
            }, 100);
        }

        // === AUTO-TRIGGER: called when loaded inside iframe ===
        // ?autoprint=1 = print without bg | ?autodownload=1 = real PDF download
        window.addEventListener('load', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1') {
                setTimeout(() => printOnly(), 600);
            } else if (params.get('autodownload') === '1') {
                setTimeout(() => downloadAsPDF(), 800);
            }
        });

        // === TRUE PDF DOWNLOAD using html2canvas + jsPDF ===
        // Background is embedded as Base64 in CSS → no HTTP request → always works from first download
        async function downloadAsPDF() {
            const { jsPDF } = window.jspdf;
            const pages = document.querySelectorAll('.page-container');
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

            document.body.classList.add('download-mode');

            // Small delay to let browser repaint with download-mode styles
            await new Promise(resolve => setTimeout(resolve, 150));

            for (let i = 0; i < pages.length; i++) {
                const canvas = await html2canvas(pages[i], {
                    scale: 3,
                    useCORS: true,
                    allowTaint: true,
                    logging: false,
                    backgroundColor: '#ffffff',
                    scrollX: 0,
                    scrollY: 0
                });
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                if (i > 0) pdf.addPage();
                pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297, undefined, 'FAST');
            }

            document.body.classList.remove('download-mode');
            pdf.save('{{ $formattedId }}.pdf');
        }
    </script>
</body>

</html>
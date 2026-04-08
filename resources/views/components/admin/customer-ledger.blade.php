<?php

use App\Models\Customer;
use App\Models\CustomerLedger;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $customerId;
    public $fromDate;
    public $toDate;
    public $currency = 'IQD';

    protected $paginationTheme = 'bootstrap';

    public function mount($customerId)
    {
        $this->customerId = $customerId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function render(): mixed
    {
        $customer = Customer::findOrFail($this->customerId);

        $query = CustomerLedger::where('customer_id', $this->customerId)
            ->where('currency', $this->currency)
            ->whereBetween('date', [$this->fromDate, $this->toDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        $entries = $query->paginate(50);

        // Calculate Opening Balance before the "from" date
        $previousBalance = CustomerLedger::where('customer_id', $this->customerId)
            ->where('currency', $this->currency)
            ->where('date', '<', $this->fromDate)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;

        // Add the customer's initial opening balance for this currency if it's the very first entry
        // and its date is within the range or if we are looking at the start of time.
        // Actually, the saving logic already creates a ledger entry for 'opening_balance'. 
        // So we just need to ensure the ledger entry is picked up.

        return view('components.admin.customer-ledger', [
            'customer' => $customer,
            'entries' => $entries,
            'previousBalance' => $previousBalance
        ]);
    }
};
?>

<div>
    <style>
        .print-header { display: none; }
        @media print {
            .app-header, .app-sidebar, .card-header .btn, .card-header input, .card-header select, .mt-4, .footer, .breadcrumb, .sidebar-toggle, .small-screen-toggle, .switcher-wrapper, .btn-primary, .btn-soft-secondary {
                display: none !important;
            }
            body { background-color: white !important; margin: 0 !important; padding: 0 !important; }
            .row, .col-12 { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .card { border: none !important; box-shadow: none !important; width: 100% !important; }
            .card-body { padding: 0 !important; }
            .table-responsive { overflow: visible !important; }
            table { width: 100% !important; border-collapse: collapse !important; border: 1px solid #000 !important; font-size: 14px !important; }
            table th, table td { border: 1px solid #000 !important; padding: 8px !important; color: black !important; }
            table thead th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; font-weight: bold !important; text-align: center !important; }
            thead { display: table-header-group !important; }
            .table-info { background-color: #e2f0fb !important; -webkit-print-color-adjust: exact; }
            .print-header { display: block !important; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        }
    </style>

    <div class="print-header">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-start">
                <img height="50" src="{{ asset('assets/images/DOKKAN.png') }}" class="mb-2">
                <h4 class="mb-0">{{ config('app.name') }}</h4>
            </div>
            <div class="text-end">
                <h2 class="mb-0">{{ __('Statement of Account') }}</h2>
                <p class="mb-0 small text-muted">{{ date('Y-m-d H:i') }}</p>
            </div>
        </div>
        <div class="row align-items-center">
            <div class="col-6">
                <strong>{{ __('Customer') }}:</strong> {{ $customer->name }}<br>
                <strong>{{ __('Phone') }}:</strong> {{ $customer->phone }}
            </div>
            <div class="col-6 text-end">
                <strong>{{ __('Period') }}:</strong> {{ $fromDate }} - {{ $toDate }}<br>
                <strong>{{ __('Currency') }}:</strong> {{ $currency }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">{{ __('Statement of Account') }}: {{ $customer->name }}</h5>
                        <p class="text-muted mb-0">{{ $customer->phone }} |
                            {{ __('Balance IQD') }}: {{ number_format($customer->opening_balance_iqd, 0) }} |
                            {{ __('Balance USD') }}: {{ number_format($customer->opening_balance_usd, 2) }}
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <select wire:model.live="currency" class="form-select form-select-sm" style="width: 100px;">
                            <option value="IQD">IQD</option>
                            <option value="USD">USD</option>
                        </select>
                        <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                        <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
                        <button onclick="openPrintModal()" class="btn btn-primary btn-sm">
                            <i class="ri-printer-line me-1"></i> {{ __('Print Statement') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th class="text-end">{{ __('Debit (+)') }}</th>
                                    <th class="text-end">{{ __('Credit (-)') }}</th>
                                    <th class="text-end">{{ __('Balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="table-info">
                                    <td colspan="2"><strong>{{ __('رصيد سابق (ما قبل') }}
                                            {{ $fromDate }})</strong></td>
                                    <td class="text-end">-</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end"><strong>{{ number_format($previousBalance, 0) }}</strong></td>
                                </tr>
                                @php $currentBalance = $previousBalance; @endphp
                                @foreach($entries as $entry)
                                    @php $currentBalance += ($entry->debit - $entry->credit); @endphp
                                    <tr>
                                        <td>{{ $entry->date }}</td>
                                        <td>
                                            {{ $entry->description }}
                                            @if($entry->ref_id)
                                                <span class="badge bg-light text-dark ms-1">#{{ $entry->ref_id }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end {{ $entry->debit > 0 ? 'text-danger' : '' }}">
                                            @if($entry->debit > 0)
                                                {{ number_format($entry->debit, 0) }}
                                                @if($entry->currency && $entry->currency !== 'IQD')
                                                    <br><small class="text-muted">{{ number_format($entry->debit, 0) }}
                                                        {{ $entry->currency }}</small>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end {{ $entry->credit > 0 ? 'text-success' : '' }}">
                                            @if($entry->credit > 0)
                                                {{ number_format($entry->credit, 0) }}
                                                @if($entry->currency && $entry->currency !== 'IQD')
                                                    <br><small class="text-muted">{{ number_format($entry->credit, 0) }}
                                                        {{ $entry->currency }}</small>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end"><strong>{{ number_format($currentBalance, 0) }} </strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">{{ __('Closing Balance') }}</th>
                                    <th class="text-end">{{ number_format($entries->sum('debit'), 0) }}</th>
                                    <th class="text-end">{{ number_format($entries->sum('credit'), 0) }}</th>
                                    <th class="text-end bg-primary-subtle">
                                        <strong>{{ number_format($currentBalance, $currency === 'USD' ? 2 : 0) }}
                                            {{ $currency }}</strong>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $entries->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Iframe for Printing/Downloading -->
    <iframe id="reportFrame" style="position: absolute; width: 210mm; height: 297mm; border: none; top: -9999px; left: -9999px; visibility: hidden;"></iframe>

    <!-- Print/Download Modal -->
    <div class="modal fade" id="reportPrintModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header" style="background: #32267d; color: white; border: none;">
                    <h5 class="modal-title">🖨️ طباعة كشف الحساب</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 30px;">
                    <p style="font-size: 13pt; margin-bottom: 25px; color: #444;">
                        كشف حساب للعميل: <strong>{{ $customer->name }}</strong>
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="triggerReportAction('print')" 
                           style="background: #32267d; color: white; border:none; padding: 12px 25px; border-radius: 8px; font-weight: bold; font-size: 11pt; cursor: pointer;">
                            🖨️ طباعة فورية
                        </button>
                        <button onclick="triggerReportAction('download')"
                           style="background: #1a7d4e; color: white; border:none; padding: 12px 25px; border-radius: 8px; font-weight: bold; font-size: 11pt; cursor: pointer;">
                            ⬇️ تحميل PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openPrintModal() {
            var modal = new bootstrap.Modal(document.getElementById('reportPrintModal'));
            modal.show();
        }

        function triggerReportAction(action) {
            // Get current filters from Livewire component
            const fromDate = @this.get('fromDate');
            const toDate = @this.get('toDate');
            const currency = @this.get('currency');
            const customerId = {{ $customer->id }};

            const baseUrl = `/admin/customers/${customerId}/report/print`;
            const queryParams = `?from_date=${fromDate}&to_date=${toDate}&currency=${currency}&auto${action}=1`;
            
            const frame = document.getElementById('reportFrame');
            frame.src = baseUrl + queryParams;

            bootstrap.Modal.getInstance(document.getElementById('reportPrintModal')).hide();
        }
    </script>
</div>
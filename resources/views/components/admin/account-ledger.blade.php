<?php

use App\Models\FinancialAccount;
use App\Models\AccountLedger;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $accountId;
    public $fromDate;
    public $toDate;

    protected $paginationTheme = 'bootstrap';

    public function mount($accountId)
    {
        $this->accountId = $accountId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function with()
    {
        $account = FinancialAccount::findOrFail($this->accountId);

        $query = AccountLedger::where('account_id', $this->accountId)
            ->whereBetween('date', [$this->fromDate, $this->toDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        $entries = $query->paginate(50);

        // Calculate Opening Balance before the "from" date
        $previousBalance = AccountLedger::where('account_id', $this->accountId)
            ->where('date', '<', $this->fromDate)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;

        return [
            'account' => $account,
            'entries' => $entries,
            'previousBalance' => $previousBalance
        ];
    }
}; ?>

<div>
    <style>
        .print-header { display: none; }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { 
                background-color: white !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                font-family: 'Calibri', 'Arial', sans-serif !important; 
                direction: rtl;
                color: #000 !important;
            }
            .app-header, .app-sidebar, .card-header, .mt-4, .footer, .breadcrumb, .sidebar-toggle, .small-screen-toggle, .switcher-wrapper, .btn {
                display: none !important;
            }
            .row, .col-12 { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .card { border: none !important; box-shadow: none !important; width: 100% !important; }
            .card-body { padding: 0 !important; }
            .table-responsive { overflow: visible !important; }
            
            /* EXCEL LIKE TABLE */
            table { 
                width: 100% !important; 
                border-collapse: collapse !important; 
                font-size: 10pt !important; 
            }
            table th, table td { 
                border: 1px solid #000000 !important; 
                padding: 4px 6px !important; 
                color: #000 !important; 
                vertical-align: middle !important;
            }
            table thead th { 
                background-color: #e6e6e6 !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                font-weight: bold !important; 
                text-align: center !important; 
                border-bottom: 2px solid #000000 !important;
            }
            
            .text-end { text-align: left !important; }
            .text-success, .text-danger { color: #000 !important; } /* Force black text for print */
            
            thead { display: table-header-group !important; }
            .table-info { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            
            /* HEADER LAYOUT */
            .print-header { 
                display: block !important; 
                margin-bottom: 15px; 
            }
            .excel-header-table { margin-bottom: 15px !important; border: none !important; }
            .excel-header-table td { border: none !important; padding: 0 !important; }
            
            .meta-table { margin-bottom: 15px !important; }
            .meta-table th { background-color: #e6e6e6 !important; font-weight: bold; width: 15%; text-align: right !important; }
            .meta-table td { width: 35%; text-align: right !important; font-weight: bold; }
            
            .badge { border: none !important; padding: 0 !important; color: #000 !important; font-weight: normal !important; background: transparent !important; }
        }
    </style>

    <div class="print-header">
        <table class="excel-header-table">
            <tr>
                <td style="text-align:right; width: 33%; vertical-align: top;">
                    <img height="40" src="{{ asset('assets/images/DOKKAN.png') }}" class="mb-1"><br>
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
                <th>{{ __('Account') }}</th>
                <td>{{ $account->name }}</td>
                <th>{{ __('Period') }}</th>
                <td dir="ltr" style="text-align: left !important;">{{ $fromDate }} <span>&rarr;</span> {{ $toDate }}</td>
            </tr>
            <tr>
                <th>{{ __('Type') }}</th>
                <td>{{ $account->type == 'cash' ? __('Cash') : __('Bank') }}</td>
                <th>{{ __('Currency') }}</th>
                <td dir="ltr" style="text-align: left !important;">{{ $account->currency }}</td>
            </tr>
        </table>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0">{{ __('Treasury Statement') }}: {{ $account->name }}</h5>
                <p class="text-muted mb-0">{{ $account->type == 'cash' ? __('Cash') : __('Bank') }} |
                    {{ __('Balance') }}:
                    <strong>{{ number_format($account->current_balance, 0) }} {{ $account->currency }}</strong>
                </p>
            </div>
            <div class="d-flex gap-2">
                <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
                <a href="{{ route('admin.accounts.ledger.print', ['id' => $account->id, 'fromDate' => $fromDate, 'toDate' => $toDate]) }}" target="_blank" class="btn btn-soft-secondary btn-sm d-flex align-items-center">
                    <i class="ri-printer-line me-1"></i> {{ __('Print') }}
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('In (+)') }}</th>
                            <th class="text-end">{{ __('Out (-)') }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-info">
                            <td colspan="2"><strong>{{ __('Balance Forward (Before') }} {{ $fromDate }})</strong></td>
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
                                        @php $ref = $entry->reference; @endphp
                                        @if($ref && !empty($ref->notes))
                                            <div class="mt-1 small text-muted">
                                                <i class="ri-information-line align-bottom me-1"></i> {{ $ref->notes }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end text-success">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 0) : '-' }}
                                </td>
                                <td class="text-end text-danger">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 0) : '-' }}
                                </td>
                                <td class="text-end">
                                    <strong>{{ number_format($currentBalance, 0) }}</strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">{{ __('Closing Balance') }}</th>
                            <th class="text-end">{{ number_format($entries->sum('debit'), 0) }}</th>
                            <th class="text-end">{{ number_format($entries->sum('credit'), 0) }}</th>
                            <th class="text-end bg-primary-subtle">
                                <strong>{{ number_format($currentBalance, 0) }} {{ $account->currency }}</strong>
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
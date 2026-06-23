<?php

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $selectedCustomerId = null;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function selectCustomer($id)
    {
        $this->selectedCustomerId = $id;
    }

    public function render(): mixed
    {
        // 1. Calculate total outstanding debts (only positive customer balances)
        $totalUsd = CustomerLedger::where('currency', 'USD')
            ->selectRaw('customer_id, SUM(debit - credit) as net_balance')
            ->groupBy('customer_id')
            ->get()
            ->filter(fn($item) => $item->net_balance > 0)
            ->sum('net_balance');

        $totalIqd = CustomerLedger::where('currency', 'IQD')
            ->selectRaw('customer_id, SUM(debit - credit) as net_balance')
            ->groupBy('customer_id')
            ->get()
            ->filter(fn($item) => $item->net_balance > 0)
            ->sum('net_balance');

        // 2. Fetch list of indebted customers with search
        $query = Customer::query()
            ->addSelect([
                'balance_iqd' => CustomerLedger::whereColumn('customer_id', 'customers.id')
                    ->where('currency', 'IQD')
                    ->selectRaw('COALESCE(SUM(debit - credit), 0)'),
                'balance_usd' => CustomerLedger::whereColumn('customer_id', 'customers.id')
                    ->where('currency', 'USD')
                    ->selectRaw('COALESCE(SUM(debit - credit), 0)'),
            ]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('phone', 'like', '%' . $this->search . '%');
            });
        }

        // Only include customers who owe us money in either USD or IQD
        $query->havingRaw('balance_iqd > 0 OR balance_usd > 0');

        $customers = $query->orderBy('name', 'asc')->paginate(10);

        // 3. Load selected customer details, unpaid invoices, and recent ledger entries
        $selectedCustomer = null;
        $unpaidInvoices = [];
        $recentTransactions = [];

        if ($this->selectedCustomerId) {
            $selectedCustomer = Customer::find($this->selectedCustomerId);
            if ($selectedCustomer) {
                // Calculate current balances again for selected customer to match
                $selectedCustomer->balance_usd = $selectedCustomer->getCurrentBalance('USD');
                $selectedCustomer->balance_iqd = $selectedCustomer->getCurrentBalance('IQD');

                // Get unpaid invoices
                $unpaidInvoices = Sale::where('customer_id', $this->selectedCustomerId)
                    ->where('type', Sale::TYPE_INVOICE)
                    ->get()
                    ->filter(fn($sale) => $sale->remainingAmount() > 0);

                // Get last 10 ledger entries
                $recentTransactions = CustomerLedger::where('customer_id', $this->selectedCustomerId)
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->limit(10)
                    ->get();
            } else {
                $this->selectedCustomerId = null;
            }
        }

        return view('components.admin.debt-management', [
            'totalUsd' => $totalUsd,
            'totalIqd' => $totalIqd,
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'unpaidInvoices' => $unpaidInvoices,
            'recentTransactions' => $recentTransactions,
        ]);
    }
};
?>

<div>
    <!-- Top Cards Summary -->
    <div class="row mb-4">
        <!-- USD Debt Card -->
        <div class="col-md-6 col-xl-6">
            <div class="card border-0 shadow-sm overflow-hidden" style="background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-indigo fs-13 mb-1">{{ __('Total Customer Debts (USD)') }}</p>
                            <h2 class="mb-0 text-indigo fw-bold">
                                $ {{ number_format($totalUsd, 2) }}
                            </h2>
                        </div>
                        <div class="avatar-lg bg-indigo-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="ri-money-dollar-circle-line fs-28 text-indigo"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- IQD Debt Card -->
        <div class="col-md-6 col-xl-6">
            <div class="card border-0 shadow-sm overflow-hidden" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-emerald fs-13 mb-1">{{ __('Total Customer Debts (IQD)') }}</p>
                            <h2 class="mb-0 text-emerald fw-bold">
                                {{ number_format($totalIqd, 0) }} <small class="fs-14">IQD</small>
                            </h2>
                        </div>
                        <div class="avatar-lg bg-emerald-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="ri-coins-line fs-28 text-emerald"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="row">
        <!-- Left Panel: Customers List -->
        <div class="col-lg-5 col-md-12 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3 pb-0">
                    <h6 class="card-title mb-0 fw-bold"><i class="ri-user-shared-line me-1"></i> {{ __('Indebted Customers') }}</h6>
                    <span class="badge bg-danger-subtle text-danger">{{ $customers->total() }} {{ __('Customers') }}</span>
                </div>
                
                <div class="card-body px-3 pt-3">
                    <!-- Search Input -->
                    <div class="mb-3">
                        <div class="position-relative">
                            <input type="search" wire:model.live="search" class="form-control form-control-sm ps-5"
                                placeholder="{{ __('Search by Name or Phone...') }}">
                            <span class="position-absolute start-0 top-50 translate-middle-y ms-3">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Customers List Group -->
                    <div class="list-group list-group-flush" style="max-height: 520px; overflow-y: auto;">
                        @forelse($customers as $customer)
                            <button wire:click="selectCustomer({{ $customer->id }})"
                                class="list-group-item list-group-item-action border-0 rounded-3 mb-2 d-flex align-items-center p-3 transition-all {{ $selectedCustomerId == $customer->id ? 'bg-primary-subtle border-start border-primary border-4 shadow-sm fw-semibold' : '' }}"
                                style="transition: all 0.2s ease;">
                                
                                <div class="avatar-sm flex-shrink-0 me-3">
                                    <span class="avatar-title rounded-circle {{ $selectedCustomerId == $customer->id ? 'bg-primary text-white' : 'bg-light text-dark' }} fs-14">
                                        {{ mb_substr($customer->name, 0, 1) }}
                                    </span>
                                </div>
                                
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="fs-14 mb-1 text-truncate">{{ $customer->name }}</h6>
                                    <p class="text-muted fs-12 mb-0 text-truncate"><i class="ri-phone-line me-1"></i> {{ $customer->phone ?: '-' }}</p>
                                </div>

                                <div class="text-end ms-2 flex-shrink-0">
                                    @if($customer->balance_usd > 0)
                                        <span class="badge bg-danger-subtle text-danger d-block mb-1 fs-12">
                                            $ {{ number_format($customer->balance_usd, 2) }}
                                        </span>
                                    @endif
                                    @if($customer->balance_iqd > 0)
                                        <span class="badge bg-danger-subtle text-danger d-block fs-12">
                                            {{ number_format($customer->balance_iqd, 0) }} IQD
                                        </span>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="text-center py-5">
                                <div class="avatar-md bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 56px; height: 56px;">
                                    <i class="ri-user-unfollow-line fs-24 text-muted"></i>
                                </div>
                                <h6 class="text-muted">{{ __('No indebted customers found.') }}</h6>
                            </div>
                        @endforelse
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $customers->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Selected Customer Details -->
        <div class="col-lg-7 col-md-12 mb-4">
            @if($selectedCustomer)
                <div class="card border-0 shadow-sm h-100">
                    <!-- Customer Header -->
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-start pt-4 px-4 pb-0 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="ri-profile-line me-1"></i> {{ $selectedCustomer->name }}</h5>
                            <p class="text-muted mb-0">
                                <i class="ri-phone-line me-1"></i> {{ $selectedCustomer->phone ?: '-' }}
                                @if($selectedCustomer->address)
                                    <span class="mx-2">|</span> <i class="ri-map-pin-line me-1"></i> {{ $selectedCustomer->address }}
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('admin.customers.ledger', $selectedCustomer->id) }}" class="btn btn-soft-primary btn-sm d-flex align-items-center">
                            <i class="ri-file-list-3-line me-1"></i> {{ __('View Full Statement') }}
                        </a>
                    </div>

                    <div class="card-body p-4">
                        <!-- Customer Debt Summary -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="fs-12 text-muted text-uppercase d-block mb-1">{{ __('Outstanding Balance (USD)') }}</span>
                                    <h4 class="fw-bold mb-0 text-danger">$ {{ number_format($selectedCustomer->balance_usd, 2) }}</h4>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="fs-12 text-muted text-uppercase d-block mb-1">{{ __('Outstanding Balance (IQD)') }}</span>
                                    <h4 class="fw-bold mb-0 text-danger">{{ number_format($selectedCustomer->balance_iqd, 0) }} IQD</h4>
                                </div>
                            </div>
                        </div>

                        <!-- Details Tabs -->
                        <ul class="nav nav-tabs nav-tabs-custom nav-primary mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab" aria-selected="true">
                                    <i class="ri-bill-line me-1"></i> {{ __('Unpaid Invoices') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger" type="button" role="tab" aria-selected="false">
                                    <i class="ri-history-line me-1"></i> {{ __('Recent Transactions') }}
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Unpaid Invoices Tab -->
                            <div class="tab-pane fade show active" id="invoices" role="tabpanel">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-hover align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>{{ __('Date') }}</th>
                                                <th class="text-end">{{ __('Invoice Value') }}</th>
                                                <th class="text-end">{{ __('Paid Amount') }}</th>
                                                <th class="text-end">{{ __('Remaining Debt') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($unpaidInvoices as $invoice)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('admin.sales.print', $invoice->id) }}" target="_blank" class="fw-bold text-primary">
                                                            #{{ $invoice->id }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $invoice->date }}</td>
                                                    <td class="text-end fw-semibold">
                                                        {{ number_format($invoice->grand_total, $invoice->currency === 'USD' ? 2 : 0) }} <small class="text-muted">{{ $invoice->currency }}</small>
                                                    </td>
                                                    <td class="text-end text-success fw-semibold">
                                                        {{ number_format($invoice->paidAmount(), $invoice->currency === 'USD' ? 2 : 0) }} <small class="text-muted">{{ $invoice->currency }}</small>
                                                    </td>
                                                    <td class="text-end text-danger fw-bold">
                                                        {{ number_format($invoice->remainingAmount(), $invoice->currency === 'USD' ? 2 : 0) }} <small class="text-danger">{{ $invoice->currency }}</small>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="ri-checkbox-circle-line text-success fs-20 d-block mb-1"></i>
                                                        {{ __('No unpaid invoices. All debts may be from opening balances.') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Recent Transactions Tab -->
                            <div class="tab-pane fade" id="ledger" role="tabpanel">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-hover align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Description') }}</th>
                                                <th class="text-end">{{ __('Debit (+)') }}</th>
                                                <th class="text-end">{{ __('Credit (-)') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentTransactions as $transaction)
                                                <tr>
                                                    <td>{{ $transaction->date }}</td>
                                                    <td class="text-wrap" style="max-width: 200px;">{{ $transaction->description }}</td>
                                                    <td class="text-end text-danger fw-semibold">
                                                        @if($transaction->debit > 0)
                                                            {{ number_format($transaction->debit, $transaction->currency === 'USD' ? 2 : 0) }} <small class="text-muted">{{ $transaction->currency }}</small>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-success fw-semibold">
                                                        @if($transaction->credit > 0)
                                                            {{ number_format($transaction->credit, $transaction->currency === 'USD' ? 2 : 0) }} <small class="text-muted">{{ $transaction->currency }}</small>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center py-4 text-muted">
                                                        {{ __('No transactions found.') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm h-100 d-flex align-items-center justify-content-center py-5 bg-light-subtle">
                    <div class="text-center p-5">
                        <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 72px; height: 72px;">
                            <i class="ri-user-search-line fs-32 text-muted"></i>
                        </div>
                        <h5 class="fw-bold mb-2">{{ __('Select a Customer') }}</h5>
                        <p class="text-muted mx-auto" style="max-width: 320px;">
                            {{ __('Please select a customer from the left list to view their detailed outstanding debt statement and unpaid invoices.') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

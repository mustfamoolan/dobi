<?php

use App\Models\Sale;
use App\Models\Customer;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $fromDate;
    public $toDate;
    public $customer_id;
    public $warehouse_id;
    public $currency;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function updated($propertyName)
    {
        $this->resetPage();
    }

    public function with()
    {
        $query = Sale::with('customer')
            ->whereBetween('date', [$this->fromDate, $this->toDate]);

        if ($this->customer_id) {
            $query->where('customer_id', $this->customer_id);
        }

        if ($this->warehouse_id) {
            $query->where('warehouse_id', $this->warehouse_id);
        }

        if ($this->currency) {
            $query->where('currency', $this->currency);
        }

        $sales_all = (clone $query)->get();
        $total_usd = $sales_all->sum(fn($s) => $s->currency === 'USD' ? $s->grand_total : ($s->grand_total / $s->exchange_rate));
        $total_iqd = $sales_all->sum(fn($s) => $s->currency === 'IQD' ? $s->grand_total : ($s->grand_total * $s->exchange_rate));

        $sales = $query->latest()->paginate(20);

        return [
            'sales' => $sales,
            'customers' => Customer::all(),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->get(),
            'total_usd' => $total_usd,
            'total_iqd' => $total_iqd,
        ];
    }
}; ?>

<div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Sales Report') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('From Date') }}</label>
                            <input type="date" wire:model.live="fromDate" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('To Date') }}</label>
                            <input type="date" wire:model.live="toDate" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Customer') }}</label>
                            <select wire:model.live="customer_id" class="form-select">
                                <option value="">{{ __('All Customers') }}</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Warehouse') }}</label>
                            <select wire:model.live="warehouse_id" class="form-select">
                                <option value="">{{ __('All Warehouses') }}</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Currency') }}</label>
                            <select wire:model.live="currency" class="form-select">
                                <option value="">{{ __('All Currencies') }}</option>
                                <option value="USD">USD</option>
                                <option value="IQD">IQD</option>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Totals -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card bg-primary-subtle border-0">
                <div class="card-body py-3">
                    <h6 class="text-primary mb-1">{{ __('Total Unified in USD') }} ($)</h6>
                    <h4 class="mb-0">{{ number_format($total_usd, 2) }} $</h4>
                    <small class="text-muted">{{ __('Converted using historical rates') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-info-subtle border-0">
                <div class="card-body py-3">
                    <h6 class="text-info mb-1">{{ __('Total Unified in IQD') }} (د.ع)</h6>
                    <h4 class="mb-0">{{ number_format($total_iqd, 0) }} د.ع</h4>
                    <small class="text-muted">{{ __('Converted using historical rates') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#{{ $sale->id }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Total Amount') }}</th>
                                    <th>{{ __('Exchange Rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sales as $sale)
                                    <tr>
                                        <td>#{{ $sale->id }}</td>
                                        <td>{{ $sale->date }}</td>
                                        <td>{{ $sale->customer->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge {{ $sale->currency == 'USD' ? 'bg-info' : 'bg-secondary' }}">
                                                {{ $sale->currency }}
                                            </span>
                                        </td>
                                        <td>{{ number_format($sale->grand_total, $sale->currency == 'USD' ? 2 : 0) }}</td>
                                        <td>{{ number_format($sale->exchange_rate, 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">{{ __('No records found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($sales->hasPages())
                    <div class="card-footer">
                        {{ $sales->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
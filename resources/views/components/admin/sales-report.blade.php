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

        $sales = $query->latest()->paginate(20);

        return [
            'sales' => $sales,
            'customers' => Customer::all(),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->get(),
            'total_amount' => $query->sum('total'),
            'total_grand' => $query->sum('grand_total'),
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
                            </select>
                        </div>
                    </div>
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
                                    <th>{{ __('Sale ID') }}</th>
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
                                        <td>{{ $sale->currency }}</td>
                                        <td>{{ number_format($sale->grand_total, 0) }}</td>
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
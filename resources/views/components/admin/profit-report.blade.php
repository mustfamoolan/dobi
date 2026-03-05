<?php

use App\Models\Sale;
use App\Models\SaleItem;
use Livewire\Volt\Component;

new class extends Component {
    public $fromDate;
    public $toDate;

    public function mount()
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function with()
    {
        $saleItems = SaleItem::whereHas('sale', function ($q) {
            $q->whereBetween('date', [$this->fromDate, $this->toDate]);
        })
            ->with(['sale', 'product'])
            ->get();

        $report = $saleItems->map(function ($item) {
            $revenue = $item->subtotal;
            $cost = $item->cost_snapshot * $item->qty;
            $profit = $revenue - $cost;
            return [
                'sale_id' => $item->sale_id,
                'date' => $item->sale->date,
                'product_name' => $item->product->name ?? 'N/A',
                'qty' => $item->qty,
                'price' => $item->price,
                'cost' => $item->cost_snapshot,
                'revenue' => $revenue,
                'total_cost' => $cost,
                'profit' => $profit,
            ];
        });

        return [
            'reportItems' => $report,
            'total_revenue' => $report->sum('revenue'),
            'total_cost' => $report->sum('total_cost'),
            'total_profit' => $report->sum('profit'),
        ];
    }
}; ?>

<div>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('Profit Report') }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('From Date') }}</label>
                    <input type="date" wire:model.live="fromDate" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('To Date') }}</label>
                    <input type="date" wire:model.live="toDate" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card bg-primary-subtle border-0">
                <div class="card-body">
                    <h6>{{ __('Total Revenue') }}</h6>
                    <h4 class="mb-0">{{ number_format($total_revenue, 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger-subtle border-0">
                <div class="card-body">
                    <h6>{{ __('Total Cost') }}</h6>
                    <h4 class="mb-0">{{ number_format($total_cost, 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success-subtle border-0">
                <div class="card-body">
                    <h6>{{ __('Gross Profit') }}</h6>
                    <h4 class="mb-0">{{ number_format($total_profit, 0) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Qty') }}</th>
                            <th>{{ __('Sale Price') }}</th>
                            <th>{{ __('Cost') }}</th>
                            <th>{{ __('Revenue') }}</th>
                            <th>{{ __('Profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportItems as $item)
                            <tr>
                                <td>{{ $item['date'] }}</td>
                                <td>{{ $item['product_name'] }}</td>
                                <td>{{ $item['qty'] }}</td>
                                <td>{{ number_format($item['price'], 0) }}</td>
                                <td>{{ number_format($item['cost'], 0) }}</td>
                                <td>{{ number_format($item['revenue'], 0) }}</td>
                                <td class="{{ $item['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($item['profit'], 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">{{ __('No records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
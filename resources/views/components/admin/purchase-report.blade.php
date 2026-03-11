<?php

use App\Models\Purchase;
use App\Models\Supplier;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $fromDate;
    public $toDate;
    public $supplier_id;
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
        $query = Purchase::with('supplier')
            ->whereBetween('date', [$this->fromDate, $this->toDate]);

        if ($this->supplier_id) {
            $query->where('supplier_id', $this->supplier_id);
        }

        if ($this->warehouse_id) {
            $query->where('warehouse_id', $this->warehouse_id);
        }

        if ($this->currency) {
            $query->where('currency', $this->currency);
        }

        $purchases_all = (clone $query)->get();
        $total_usd = $purchases_all->sum(fn($p) => $p->currency === 'USD' ? $p->grand_total : ($p->grand_total / $p->exchange_rate));
        $total_iqd = $purchases_all->sum(fn($p) => $p->currency === 'IQD' ? $p->grand_total : ($p->grand_total * $p->exchange_rate));

        $purchases = $query->latest()->paginate(20);

        return [
            'purchases' => $purchases,
            'suppliers' => Supplier::all(),
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
                    <h5 class="card-title mb-0">{{ __('Purchases Report') }}</h5>
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
                            <label class="form-label">{{ __('Supplier') }}</label>
                            <select wire:model.live="supplier_id" class="form-select">
                                <option value="">{{ __('All Suppliers') }}</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
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
                                    <th>{{ __('Purchase ID') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Supplier') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Total Amount') }}</th>
                                    <th>{{ __('Exchange Rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchases as $purchase)
                                    <tr>
                                        <td>#{{ $purchase->id }}</td>
                                        <td>{{ $purchase->date }}</td>
                                        <td>{{ $purchase->supplier->name ?? 'N/A' }}</td>
                                        <td>
                                            <span
                                                class="badge {{ $purchase->currency == 'USD' ? 'bg-info' : 'bg-secondary' }}">
                                                {{ $purchase->currency }}
                                            </span>
                                        </td>
                                        <td>{{ number_format($purchase->grand_total, $purchase->currency == 'USD' ? 2 : 0) }}
                                        </td>
                                        <td>{{ number_format($purchase->exchange_rate, 0) }}</td>
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
                @if($purchases->hasPages())
                    <div class="card-footer">
                        {{ $purchases->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
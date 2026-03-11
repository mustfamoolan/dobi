<?php

use App\Models\Sale;
use App\Models\SaleItem;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $fromDate;
    public $toDate;
    public $currency = '';

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function with()
    {
        $baseQuery = SaleItem::whereHas('sale', function ($q) {
            $q->whereBetween('date', [$this->fromDate, $this->toDate]);
        });

        $allItems = (clone $baseQuery)->with(['sale', 'product'])->get();

        $usdItems = $allItems->filter(fn($item) => $item->sale->currency === 'USD');
        $iqdItems = $allItems->filter(fn($item) => $item->sale->currency === 'IQD');

        $calculateTotals = function ($items) {
            $revenue = $items->sum('subtotal');
            $cost = $items->sum(fn($item) => ($item->cost_snapshot ?? 0) * $item->qty);

            // Unified totals (converting mixed currencies)
            $unified_usd_rev = $items->sum(fn($item) => $item->sale->currency === 'USD' ? $item->subtotal : ($item->subtotal / $item->sale->exchange_rate));
            $unified_usd_cost = $items->sum(fn($item) => $item->sale->currency === 'USD' ? (($item->cost_snapshot ?? 0) * $item->qty) : ((($item->cost_snapshot ?? 0) * $item->qty) / $item->sale->exchange_rate));

            $unified_iqd_rev = $items->sum(fn($item) => $item->sale->currency === 'IQD' ? $item->subtotal : ($item->subtotal * $item->sale->exchange_rate));
            $unified_iqd_cost = $items->sum(fn($item) => $item->sale->currency === 'IQD' ? (($item->cost_snapshot ?? 0) * $item->qty) : ((($item->cost_snapshot ?? 0) * $item->qty) * $item->sale->exchange_rate));

            return [
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $revenue - $cost,
                'unified_usd' => [
                    'revenue' => $unified_usd_rev,
                    'cost' => $unified_usd_cost,
                    'profit' => $unified_usd_rev - $unified_usd_cost,
                ],
                'unified_iqd' => [
                    'revenue' => $unified_iqd_rev,
                    'cost' => $unified_iqd_cost,
                    'profit' => $unified_iqd_rev - $unified_iqd_cost,
                ]
            ];
        };

        $usdTotals = $calculateTotals($usdItems); // These are items already in USD
        $iqdTotals = $calculateTotals($iqdItems); // These are items already in IQD

        $grandTotals = $calculateTotals($allItems);

        $query = (clone $baseQuery)->with(['sale', 'product']);
        // We no longer strictly filter the query by currency here if we want a unified view
        // But we might want to keep it if the user explicitly wants to see ONLY those invoices.
        // However, the user request "ليس فقط تضع عمله بل يجب ان تحول المبلغ" suggests they want 
        // to see the values converted when a currency is selected.
        if ($this->currency) {
            // If they selected a specific currency, we show ALL items but converted to that currency
            // So we don't filter the base query.
        }

        $reportItems = $query->latest()->paginate(20);

        return [
            'reportItems' => $reportItems,
            'usdTotals' => $usdTotals,
            'iqdTotals' => $iqdTotals,
            'grandTotals' => $grandTotals,
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
                <div class="col-md-3">
                    <label class="form-label">{{ __('From Date') }}</label>
                    <input type="date" wire:model.live="fromDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('To Date') }}</label>
                    <input type="date" wire:model.live="toDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Currency') }}</label>
                    <select wire:model.live="currency" class="form-select">
                        <option value="">{{ __('All Currencies') }}</option>
                        <option value="USD">USD - دولار</option>
                        <option value="IQD">IQD - دينار</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <h5 class="mb-3 text-primary"> <i class="ri-funds-line"></i> {{ __('Unified Financial Summary') }} (الخلاصة
                المالية الموحدة)</h5>
            <p class="text-muted small">
                {{ __('All amounts are converted using the exchange rate at the time of each sale.') }}
            </p>
        </div>

        <!-- Unified USD Column -->
        <div class="col-md-6">
            <div class="card border-primary shadow-sm">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0">{{ __('Unified Total in USD') }} (الإجمالي الموحد بالدولار)</h6>
                </div>
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Revenue') }}:</span>
                        <span
                            class="fw-bold text-primary">{{ number_format($grandTotals['unified_usd']['revenue'], 2) }}
                            $</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Total Cost') }}:</span>
                        <span class="fw-bold text-danger">{{ number_format($grandTotals['unified_usd']['cost'], 2) }}
                            $</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">{{ __('Gross Profit') }}:</span>
                        <span class="fw-bold text-success">{{ number_format($grandTotals['unified_usd']['profit'], 2) }}
                            $</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unified IQD Column -->
        <div class="col-md-6">
            <div class="card border-info shadow-sm">
                <div class="card-header bg-info text-white py-2">
                    <h6 class="mb-0">{{ __('Unified Total in IQD') }} (الإجمالي الموحد بالدينار)</h6>
                </div>
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Revenue') }}:</span>
                        <span
                            class="fw-bold text-primary">{{ number_format($grandTotals['unified_iqd']['revenue'], 0) }}
                            د.ع</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Total Cost') }}:</span>
                        <span class="fw-bold text-danger">{{ number_format($grandTotals['unified_iqd']['cost'], 0) }}
                            د.ع</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">{{ __('Gross Profit') }}:</span>
                        <span class="fw-bold text-success">{{ number_format($grandTotals['unified_iqd']['profit'], 0) }}
                            د.ع</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Original Currency Breakdown (Optional/Smaller) -->
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-muted"><i class="ri-pie-chart-line"></i> {{ __('Breakdown by Original Currency') }} (حسب
                عملة الفاتورة الأصلية)</h6>
        </div>
        <div class="col-md-6">
            <div class="card bg-light border-0 shadow-none">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small">{{ __('USD Sales Only') }}:</span>
                        <span class="fw-bold text-success small">{{ number_format($usdTotals['profit'], 2) }} $
                            {{ __('Profit') }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-light border-0 shadow-none">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small">{{ __('IQD Sales Only') }}:</span>
                        <span class="fw-bold text-success small">{{ number_format($iqdTotals['profit'], 0) }} د.ع
                            {{ __('Profit') }}</span>
                    </div>
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
                            <th>{{ __('Currency') }}</th>
                            <th>{{ __('Sale Price') }}</th>
                            <th>{{ __('Cost') }}</th>
                            <th>{{ __('Revenue') }}</th>
                            <th>{{ __('Profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportItems as $item)
                            @php
                                $originalCurrency = $item->sale->currency;
                                $rate = $item->sale->exchange_rate ?: 1;

                                $displayPrice = $item->price;
                                $displayCost = $item->cost_snapshot;
                                $displaySubtotal = $item->subtotal;

                                // Target Currency Conversion
                                if ($this->currency === 'USD' && $originalCurrency === 'IQD') {
                                    $displayPrice /= $rate;
                                    $displayCost /= $rate;
                                    $displaySubtotal /= $rate;
                                } elseif ($this->currency === 'IQD' && $originalCurrency === 'USD') {
                                    $displayPrice *= $rate;
                                    $displayCost *= $rate;
                                    $displaySubtotal *= $rate;
                                }

                                $displayProfit = $displaySubtotal - ($displayCost * $item->qty);
                                $displayCurrency = $this->currency ?: $originalCurrency;
                            @endphp
                            <tr>
                                <td>{{ $item->sale->date }}</td>
                                <td>{{ $item->product->name ?? 'N/A' }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>
                                    <span class="badge {{ $originalCurrency == 'USD' ? 'bg-info' : 'bg-secondary' }}">
                                        {{ $originalCurrency }}
                                    </span>
                                </td>
                                <td>{{ number_format($displayPrice, $displayCurrency == 'USD' ? 2 : 0) }}
                                    {{ $displayCurrency == 'USD' ? '$' : 'د.ع' }}</td>
                                <td>{{ number_format($displayCost, $displayCurrency == 'USD' ? 2 : 0) }}
                                    {{ $displayCurrency == 'USD' ? '$' : 'د.ع' }}</td>
                                <td>{{ number_format($displaySubtotal, $displayCurrency == 'USD' ? 2 : 0) }}
                                    {{ $displayCurrency == 'USD' ? '$' : 'د.ع' }}</td>
                                <td class="{{ $displayProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($displayProfit, $displayCurrency == 'USD' ? 2 : 0) }}
                                    {{ $displayCurrency == 'USD' ? '$' : 'د.ع' }}
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
        @if($reportItems->hasPages())
            <div class="card-footer">
                {{ $reportItems->links() }}
            </div>
        @endif
    </div>
</div>
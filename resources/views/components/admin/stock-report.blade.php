<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public $warehouse_id = '';

    public function with()
    {
        // Use a subquery to calculate current stock so we can filter by it in SQL
        $products = Product::select('products.*')
            ->selectSub(function ($query) {
                $query->from('stock_movements')
                    ->selectRaw('SUM(qty_in) - SUM(qty_out)')
                    ->whereColumn('product_id', 'products.id');

                if ($this->warehouse_id) {
                    $query->where('warehouse_id', $this->warehouse_id);
                }
            }, 'current_stock')
            ->havingRaw('current_stock <= stock_alert OR current_stock <= 5')
            ->with('category')
            ->get();

        return [
            'products' => $products,
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->get()
        ];
    }
}; ?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ __('Stock Alert Report') }}</h5>
            <div style="width: 250px;">
                <select wire:model.live="warehouse_id" class="form-select form-select-sm">
                    <option value="">{{ __('All Warehouses') }}</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Current Stock') }}</th>
                            <th>{{ __('Alert Threshold') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->category->name ?? 'N/A' }}</td>
                                <td class="text-danger fw-bold">{{ $product->current_stock ?? 0 }}</td>
                                <td>{{ $product->stock_alert }}</td>
                                <td>
                                    @if(($product->current_stock ?? 0) <= 0)
                                        <span class="badge bg-danger">{{ __('Out of Stock') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('Low Stock') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">{{ __('General inventory levels are healthy.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
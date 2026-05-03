<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public $warehouse_id = '';

    public function with()
    {
        // 1. Calculate stock PER PRODUCT PER WAREHOUSE
        $stockQuery = DB::table('stock_movements')
            ->select('product_id', 'warehouse_id', DB::raw('IFNULL(SUM(qty_in), 0) - IFNULL(SUM(qty_out), 0) as current_stock'))
            ->groupBy('product_id', 'warehouse_id');

        if ($this->warehouse_id) {
            $stockQuery->where('warehouse_id', $this->warehouse_id);
        }

        // 2. INNER JOIN products to get ONLY relevant products that have stock records in the targeted warehouse(s)
        $productsWithMovements = Product::joinSub($stockQuery, 'stock', function ($join) {
                $join->on('products.id', '=', 'stock.product_id');
            })
            ->join('warehouses', 'stock.warehouse_id', '=', 'warehouses.id')
            ->select(
                'products.*',
                'warehouses.name as warehouse_name',
                'stock.current_stock'
            )
            ->whereRaw('stock.current_stock <= products.stock_alert OR stock.current_stock <= 5')
            ->with('category')
            ->get();

        // 3. Include products with ZERO stock movements entirely, only if viewing "All Warehouses"
        if (empty($this->warehouse_id)) {
            $productsWithoutMovements = Product::whereDoesntHave('stockMovements')
                ->select('products.*')
                ->selectRaw('0 as current_stock')
                ->selectRaw('NULL as warehouse_name')
                ->with('category')
                ->get();
            
            $products = $productsWithMovements->concat($productsWithoutMovements);
        } else {
            $products = $productsWithMovements;
        }

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
                            <th>{{ __('Warehouse') }}</th>
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
                                <td>
                                    @if($product->warehouse_name)
                                        <span class="badge bg-info-subtle text-info">{{ $product->warehouse_name }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">{{ __('Unassigned / All') }}</span>
                                    @endif
                                </td>
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
                                <td colspan="6" class="text-center py-4">{{ __('General inventory levels are healthy.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
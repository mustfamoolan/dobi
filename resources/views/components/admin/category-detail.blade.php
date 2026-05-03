<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $id;
    public $search = '';

    protected $paginationTheme = 'bootstrap';

    public function mount($id)
    {
        $this->id = $id;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render(): mixed
    {
        $category = Category::findOrFail($this->id);
        
        $products = $category->products()
            ->where(function($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->paginate(15);

        return view('components.admin.category-detail', [
            'category' => $category,
            'products' => $products
        ]);
    }
};
?>

<div>
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">{{ __('Category Details') }}: {{ $category->name }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">{{ __('Categories') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('View Details') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-0 align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">{{ __('Products in this Category') }}</h4>
                    <div class="flex-shrink-0">
                        <div class="d-flex flex-wrap gap-2">
                             <input type="search" wire:model.live="search" class="form-control form-control-sm"
                                placeholder="{{ __('Search Products...') }}">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive table-card">
                        <table class="table table-hover align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Product Name') }}</th>
                                    <th>{{ __('Stock per Warehouse') }}</th>
                                    <th>{{ __('Unit') }}</th>
                                    <th>{{ __('Cost') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th class="text-center">{{ __('Current Stock') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    <tr>
                                        <td>{{ $product->name }}</td>
                                        <td>
                                            @php
                                                $stocks = \App\Models\StockMovement::query()
                                                    ->select('warehouse_id')
                                                    ->selectRaw('SUM(qty_in) - SUM(qty_out) as total')
                                                    ->where('product_id', $product->id)
                                                    ->groupBy('warehouse_id')
                                                    ->havingRaw('SUM(qty_in) - SUM(qty_out) > 0')
                                                    ->get();
                                                $warehouseNames = \App\Models\Warehouse::whereIn('id', $stocks->pluck('warehouse_id'))->pluck('name', 'id');
                                            @endphp
                                            @forelse($stocks as $st)
                                                <small class="d-block text-muted">
                                                    {{ $warehouseNames[$st->warehouse_id] ?? 'Unknown' }}: <strong>{{ number_format($st->total, 0) }}</strong>
                                                </small>
                                            @empty
                                                <span class="text-danger small">{{ __('No Stock') }}</span>
                                            @endforelse
                                        </td>
                                        <td>{{ $product->unit }}</td>
                                        <td>{{ number_format($product->cost, 2) }} {{ $product->currency }}</td>
                                        <td>{{ number_format($product->price, 2) }} {{ $product->currency }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $product->stock <= $product->stock_alert ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} fs-12">
                                                {{ $product->stock }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($product->is_active)
                                                <span class="badge bg-success-subtle text-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.products.history', $product->id) }}" class="btn btn-sm btn-soft-info" title="{{ __('Stock History') }}">
                                                <i class="ri-history-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ri-search-line fs-24 mb-2"></i>
                                                <p>{{ __('No products found in this category.') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $products->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

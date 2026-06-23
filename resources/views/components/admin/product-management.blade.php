<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';
    public $productId;
    public $name, $category_id, $currency = 'IQD', $cost = 0, $price = 0, $unit = 'Pcs', $stock_alert = 0, $is_active = true;
    public $opening_stock = 0, $warehouse_id; // Only used during creation
    public $isEditMode = false;

    // Category Properties
    public $categoryName;
    public $categoryIdForEdit;
    public $isCategoryEditMode = false;

    // Stock Adjustment Properties
    public $adj_product_id;
    public $adj_warehouse_id = '';
    public $adj_qty = 1;
    public $adj_type = 'in';
    public $adj_note = '';
    public $adj_current_stock = 0;

    // Filter Properties
    public $filter_category_id = '';
    public $filter_warehouse_id = '';

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterCategoryId()
    {
        $this->resetPage();
    }

    public function updatingFilterWarehouseId()
    {
        $this->resetPage();
    }

    public function openAdjustmentModal($productId)
    {
        $this->reset(['adj_warehouse_id', 'adj_note', 'adj_current_stock']);
        $this->adj_product_id = $productId;
        $this->adj_qty = 1;
        $this->adj_type = 'in';
        $this->dispatch('open-adjustment-modal');
    }

    public function updatedAdjWarehouseId()
    {
        if ($this->adj_product_id && $this->adj_warehouse_id) {
            $product = \App\Models\Product::find($this->adj_product_id);
            $this->adj_current_stock = $product ? $product->stockInWarehouse($this->adj_warehouse_id) : 0;
        } else {
            $this->adj_current_stock = 0;
        }
    }

    public function adjustStock()
    {
        $this->validate([
            'adj_product_id' => 'required|exists:products,id',
            'adj_warehouse_id' => 'required|exists:warehouses,id',
            'adj_qty' => 'required|numeric|min:0.001',
            'adj_type' => 'required|in:in,out',
        ]);

        StockMovement::create([
            'product_id' => $this->adj_product_id,
            'warehouse_id' => $this->adj_warehouse_id,
            'qty_in' => $this->adj_type == 'in' ? $this->adj_qty : 0,
            'qty_out' => $this->adj_type == 'out' ? $this->adj_qty : 0,
            'ref_type' => 'adjustment',
            'note' => $this->adj_note ?: __('Stock Adjustment'),
            'created_by' => Auth::id(),
        ]);

        $product = Product::find($this->adj_product_id);
        $warehouse = \App\Models\Warehouse::find($this->adj_warehouse_id);

        \App\Services\ActivityLogger::log(
            'updated',
            __('Adjusted stock for :product in :warehouse (:type :qty)', [
                'product' => $product->name,
                'warehouse' => $warehouse->name,
                'type' => $this->adj_type == 'in' ? '+' : '-',
                'qty' => $this->adj_qty
            ]),
            $product
        );

        session()->flash('success', __('Stock adjusted successfully.'));
        $this->dispatch('close-adjustment-modal');
        $this->reset(['adj_product_id', 'adj_warehouse_id', 'adj_qty', 'adj_type', 'adj_note', 'adj_current_stock']);
    }

    public function openModal()
    {
        $this->reset(['name', 'category_id', 'currency', 'cost', 'price', 'unit', 'stock_alert', 'is_active', 'opening_stock', 'productId', 'isEditMode']);
        $this->currency = 'IQD'; // Default currency
        $this->dispatch('open-product-modal');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->currency = $product->currency ?? 'IQD';
        $this->cost = $product->cost;
        $this->price = $product->price;
        $this->unit = $product->unit;
        $this->stock_alert = $product->stock_alert;
        $this->is_active = $product->is_active;
        $this->isEditMode = true;
        $this->dispatch('open-product-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'currency' => 'required|in:USD,IQD',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'stock_alert' => 'required|numeric|min:0',
            'opening_stock' => $this->isEditMode ? 'nullable' : 'required|numeric|min:0',
            'warehouse_id' => $this->isEditMode ? 'nullable' : 'required_if:opening_stock,>0|exists:warehouses,id',
        ]);

        $data = [
            'name' => $this->name,
            'category_id' => $this->category_id,
            'currency' => $this->currency,
            'cost' => $this->cost,
            'price' => $this->price,
            'unit' => $this->unit,
            'stock_alert' => $this->stock_alert,
            'is_active' => $this->is_active,
            'updated_by' => Auth::id(),
        ];

        if ($this->isEditMode) {
            $product = Product::findOrFail($this->productId);
            $product->update($data);

            \App\Services\ActivityLogger::log('updated', __('Updated product: :name', ['name' => $product->name]), $product);
            
            session()->flash('success', __('Product updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            $product = Product::create($data);

            // Create Opening Stock record
            if ($this->opening_stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $this->warehouse_id,
                    'qty_in' => $this->opening_stock,
                    'ref_type' => 'opening',
                    'note' => __('Opening Stock'),
                    'created_by' => Auth::id(),
                ]);
            }

            \App\Services\ActivityLogger::log('created', __('Created new product: :name', ['name' => $product->name]), $product);

            session()->flash('success', __('Product created successfully.'));
        }

        $this->dispatch('close-product-modal');
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);
        $name = $product->name;

        // Check for dependencies (sales, etc) - for now just stock movements beyond opening
        if ($product->stockMovements()->where('ref_type', '!=', 'opening')->count() > 0) {
            session()->flash('error', __('Cannot delete product with existing transactions.'));
            return;
        }

        $product->delete();
        
        \App\Services\ActivityLogger::log('deleted', __('Deleted product: :name', ['name' => $name]));

        session()->flash('success', __('Product deleted successfully.'));
    }

    public function updateCategoryOrder($orderedIds)
    {
        foreach ($orderedIds as $index => $id) {
            Category::where('id', $id)->update(['sort_order' => $index]);
        }
    }

    public function openCategoryModal()
    {
        $this->reset(['categoryName', 'categoryIdForEdit', 'isCategoryEditMode']);
        $this->dispatch('open-category-modal');
    }

    public function editCategory($id)
    {
        $category = Category::findOrFail($id);
        $this->categoryIdForEdit = $category->id;
        $this->categoryName = $category->name;
        $this->isCategoryEditMode = true;
        $this->dispatch('open-category-modal');
    }

    public function saveCategory()
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
        ]);

        $data = [
            'name' => $this->categoryName,
            'updated_by' => Auth::id(),
        ];

        if ($this->isCategoryEditMode) {
            $category = Category::findOrFail($this->categoryIdForEdit);
            $category->update($data);
            \App\Services\ActivityLogger::log('updated', __('Updated category: :name', ['name' => $category->name]), $category);
            session()->flash('success', __('Category updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            $category = Category::create($data);
            \App\Services\ActivityLogger::log('created', __('Created category: :name', ['name' => $category->name]), $category);
            session()->flash('success', __('Category created successfully.'));
        }

        $this->dispatch('close-category-modal');
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        $name = $category->name;
        if ($category->products()->count() > 0) {
            session()->flash('error', __('Cannot delete category with associated products.'));
            return;
        }
        $category->delete();
        \App\Services\ActivityLogger::log('deleted', __('Deleted category: :name', ['name' => $name]));
        session()->flash('success', __('Category deleted successfully.'));
    }

    public function render(): mixed
    {
        $products = Product::with('category')
            ->when($this->filter_category_id && $this->filter_category_id !== 'all', function($q) {
                $q->where('category_id', $this->filter_category_id);
            })
            ->when($this->filter_warehouse_id, function($q) {
                // Return products that have stock movements in the selected warehouse
                $q->whereHas('stockMovements', function($q2) {
                    $q2->where('warehouse_id', $this->filter_warehouse_id);
                });
            })
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('components.admin.product-management', [
            'products' => $products,
            'categories' => $categories
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center">
                @if(!empty($filter_category_id) || !empty($search))
                    <button wire:click="$set('filter_category_id', ''); $set('search', '')" class="btn btn-soft-secondary btn-sm me-3 rounded-pill px-3">
                        <i class="ri-arrow-left-line align-bottom me-1"></i> {{ __('Back to Categories') }}
                    </button>
                @endif
                <h5 class="card-title mb-0 fw-bold">
                    @if(!empty($filter_category_id))
                        @if($filter_category_id === 'all')
                            {{ __('All Products') }}
                        @else
                            {{ __('Category') }}: {{ $categories->firstWhere('id', $filter_category_id)?->name }}
                        @endif
                    @else
                        {{ __('Product Management') }}
                    @endif
                </h5>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if(empty($filter_category_id) && empty($search))
                    <button wire:click="openCategoryModal" class="btn btn-secondary btn-sm">
                        <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Category') }}
                    </button>
                @endif
                @if(!empty($filter_category_id) || !empty($search))
                    <select wire:model.live="filter_category_id" class="form-select form-select-sm" style="width: auto;">
                        <option value="">{{ __('Select Category') }}</option>
                        <option value="all">{{ __('All Categories') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filter_warehouse_id" class="form-select form-select-sm" style="width: auto;">
                        <option value="">{{ __('All Warehouses') }}</option>
                        @foreach(\App\Models\Warehouse::where('is_active', true)->get() as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Products...') }}" style="width: auto;">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Product') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success mt-2">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger mt-2">{{ session('error') }}</div>
            @endif

            @if(empty($filter_category_id) && empty($search))
                <!-- CATEGORIES GRID VIEW -->
                <div class="row g-4" id="categories-container">
                    <!-- ALL PRODUCTS CARD -->
                    <div class="col-xl-3 col-lg-4 col-sm-6 static-card">
                        <div class="card h-100 shadow-sm hover-shadow border border-light-subtle rounded-4" 
                             style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.08)';"
                             onmouseout="this.style.transform='none'; this.style.boxShadow='none';"
                             wire:click="$set('filter_category_id', 'all')">
                            <div class="card-body text-center p-4">
                                <div class="avatar-md mx-auto mb-3 bg-success-subtle text-success rounded-4 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="ri-grid-fill fs-2" style="font-size: 2rem !important;"></i>
                                </div>
                                <h5 class="fs-16 mb-2 text-dark fw-bold">{{ __('All Products') }}</h5>
                                <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">
                                    {{ \App\Models\Product::count() }} {{ __('Products') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- INDIVIDUAL CATEGORIES CARDS -->
                    @foreach($categories as $cat)
                        <div class="col-xl-3 col-lg-4 col-sm-6 sortable-card" data-id="{{ $cat->id }}">
                            <div class="card h-100 shadow-sm hover-shadow border border-light-subtle rounded-4" 
                                 style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.08)';"
                                 onmouseout="this.style.transform='none'; this.style.boxShadow='none';"
                                 wire:click="$set('filter_category_id', {{ $cat->id }})">
                                <div class="card-body text-center p-4">
                                    <div class="avatar-md mx-auto mb-3 bg-primary-subtle text-primary rounded-4 d-flex align-items-center justify-content-center" 
                                         style="width: 60px; height: 60px;">
                                        <i class="ri-folder-open-fill fs-2" style="font-size: 2rem !important;"></i>
                                    </div>
                                    <h5 class="fs-16 mb-2 text-dark fw-bold">{{ $cat->name }}</h5>
                                    <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill">
                                        {{ $cat->products_count }} {{ __('Products') }}
                                    </span>
                                </div>
                                <div class="card-footer bg-transparent border-top-0 d-flex justify-content-center gap-2 pb-4">
                                    <button wire:click.stop="editCategory({{ $cat->id }})" class="btn btn-sm btn-soft-info px-3" title="{{ __('Edit') }}">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button wire:click.stop="deleteCategory({{ $cat->id }})"
                                        onclick="event.stopPropagation(); return confirm('{{ __('Are you sure?') }}')" class="btn btn-sm btn-soft-danger px-3" title="{{ __('Delete') }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- PRODUCTS TABLE LIST VIEW -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('Stock') }}</th>
                                <th>{{ __('Cost') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <h6 class="fs-14 mb-0">{{ $product->name }}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $product->category->name }}</td>
                                    <td>
                                        @php $totalStock = $product->currentStock(); @endphp
                                        <span
                                            class="badge {{ $totalStock <= $product->stock_alert ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                            {{ number_format($totalStock, 0) }} {{ $product->unit }}
                                        </span>
                                        <div class="mt-1">
                                            @foreach(\App\Models\Warehouse::where('is_active', true)->get() as $wh)
                                                @php $whStock = $product->currentStock($wh->id); @endphp
                                                @if($whStock != 0)
                                                    <small class="d-block text-muted" style="font-size: 0.75rem;">
                                                        {{ $wh->name }}: {{ number_format($whStock, 0) }}
                                                    </small>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>{{ number_format($product->cost, $product->currency === 'USD' ? 2 : 0) }} {{ $product->currency }}</td>
                                    <td>{{ number_format($product->price, $product->currency === 'USD' ? 2 : 0) }} {{ $product->currency }}</td>
                                    <td>
                                        <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $product->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.products.history', $product->id) }}" class="btn btn-sm btn-soft-warning" title="{{ __('Stock History') }}">
                                            <i class="ri-history-line"></i>
                                        </a>
                                        <button wire:click="openAdjustmentModal({{ $product->id }})" class="btn btn-sm btn-soft-primary" title="{{ __('Adjust Stock') }}">
                                            <i class="ri-settings-4-line"></i>
                                        </button>
                                        <button wire:click="edit({{ $product->id }})" class="btn btn-sm btn-soft-info" title="{{ __('Edit') }}"><i
                                                class="ri-edit-line"></i></button>
                                        <button wire:click="delete({{ $product->id }})"
                                            onclick="return confirm('{{ __('Are you sure?') }}')" class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}"><i
                                                class="ri-delete-bin-line"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted italic">
                                        {{ __('No products found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            @endif

    <!-- Product Modal -->
    <div wire:ignore.self class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel">
                        {{ $isEditMode ? __('Edit Product') : __('Add New Product') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Product Name') }}</label>
                                <input type="text" wire:model="name"
                                    class="form-control @error('name') is-invalid @enderror">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Category') }}</label>
                                <select wire:model="category_id"
                                    class="form-select @error('category_id') is-invalid @enderror">
                                    <option value="">{{ __('Select Category') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Unit') }}</label>
                                <input type="text" wire:model="unit" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('Currency') }}</label>
                                <select wire:model="currency" class="form-select @error('currency') is-invalid @enderror">
                                    <option value="USD">USD</option>
                                    <option value="IQD">IQD</option>
                                </select>
                                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('Cost Price') }}</label>
                                <input type="number" step="{{ $currency === 'USD' ? '0.01' : '1' }}" wire:model="cost"
                                    class="form-control @error('cost') is-invalid @enderror">
                                @error('cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('Selling Price') }}</label>
                                <input type="number" step="{{ $currency === 'USD' ? '0.01' : '1' }}" wire:model="price"
                                    class="form-control @error('price') is-invalid @enderror">
                                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('Stock Alert Level') }}</label>
                                <input type="number" step="1" wire:model="stock_alert"
                                    class="form-control @error('stock_alert') is-invalid @enderror">
                                @error('stock_alert') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if(!$isEditMode)
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Opening Stock Qty') }}</label>
                                    <input type="number" step="1" wire:model="opening_stock"
                                        class="form-control @error('opening_stock') is-invalid @enderror">
                                    @error('opening_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">{{ __('Warehouse (for Opening Stock)') }}</label>
                                    <select wire:model="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror">
                                        <option value="">-- {{ __('Select Warehouse') }} --</option>
                                        @foreach(\App\Models\Warehouse::where('is_active', true)->get() as $wh)
                                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" wire:model="is_active">
                                    <label class="form-check-label">{{ __('Product is Active') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ $isEditMode ? __('Update') : __('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div wire:ignore.self class="modal fade" id="adjustmentModal" tabindex="-1" aria-labelledby="adjustmentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adjustmentModalLabel">{{ __('Stock Adjustment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="adjustStock">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Select Warehouse') }}</label>
                            <select wire:model.live="adj_warehouse_id" class="form-select @error('adj_warehouse_id') is-invalid @enderror">
                                <option value="">-- {{ __('Select Warehouse') }} --</option>
                                @foreach(\App\Models\Warehouse::where('is_active', true)->get() as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                            @error('adj_warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        @if($adj_warehouse_id)
                        <div class="alert alert-info py-2 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ __('Current Quantity in Warehouse') }}:</span>
                                <strong>{{ (float) $adj_current_stock }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ __('Adjustment Quantity') }}:</span>
                                <strong class="{{ $adj_type == 'in' ? 'text-success' : 'text-danger' }}">
                                    {{ $adj_type == 'in' ? '+' : '-' }}{{ (float) ($adj_qty ?: 0) }}
                                </strong>
                            </div>
                            <hr class="my-1 border-info">
                            <div class="d-flex justify-content-between">
                                <span>{{ __('Quantity After') }}:</span>
                                <strong>
                                    {{ (float) ($adj_type == 'in' ? $adj_current_stock + (float) ($adj_qty ?: 0) : $adj_current_stock - (float) ($adj_qty ?: 0)) }}
                                </strong>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Adjustment Type') }}</label>
                            <select wire:model.live="adj_type" class="form-select">
                                <option value="in">{{ __('Adjustment In') }} (+)</option>
                                <option value="out">{{ __('Adjustment Out') }} (-)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Adjustment Qty') }}</label>
                            <input type="number" step="0.001" wire:model.live="adj_qty" class="form-control  @error('adj_qty') is-invalid @enderror">
                            @error('adj_qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Note') }}</label>
                            <textarea wire:model="adj_note" class="form-control"></textarea>
                        </div>
                        @else
                            <p class="text-muted text-center my-4">{{ __('Please select a warehouse first to adjust stock.') }}</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" @if(!$adj_warehouse_id) disabled @endif>{{ __('Adjust Stock') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div wire:ignore.self class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">
                        {{ $isCategoryEditMode ? __('Edit Category') : __('Add New Category') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="saveCategory">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Category Name') }}</label>
                            <input type="text" wire:model="categoryName"
                                class="form-control @error('categoryName') is-invalid @enderror">
                            @error('categoryName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ $isCategoryEditMode ? __('Update') : __('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<style>
    .sortable-ghost .card {
        background-color: rgba(50, 38, 125, 0.03) !important;
        border: 2px dashed #32267d !important;
        box-shadow: none !important;
        transform: none !important;
        height: 100% !important;
        min-height: 160px;
    }
    
    .sortable-ghost .card * {
        visibility: hidden !important;
    }
    
    .sortable-chosen .card {
        box-shadow: 0 15px 30px rgba(50, 38, 125, 0.15) !important;
        border: 1.5px solid #32267d !important;
        transform: translateY(-5px);
    }

    .sortable-drag .card {
        opacity: 0.95 !important;
        box-shadow: 0 20px 40px rgba(50, 38, 125, 0.2) !important;
        border: 2px solid #32267d !important;
        transform: rotate(2deg) scale(1.02);
        cursor: grabbing !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
@script
<script>
    const initSortable = () => {
        let container = document.getElementById('categories-container');
        if (container && typeof Sortable !== 'undefined') {
            if (container._sortable) {
                container._sortable.destroy();
            }
            container._sortable = new Sortable(container, {
                animation: 150,
                draggable: '.sortable-card',
                filter: '.static-card',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function (evt) {
                    let orderedIds = [];
                    container.querySelectorAll('.sortable-card').forEach(function(el) {
                        let id = el.getAttribute('data-id');
                        if (id) {
                            orderedIds.push(id);
                        }
                    });
                    $wire.updateCategoryOrder(orderedIds);
                }
            });
        }
    };

    setTimeout(initSortable, 100);
    Livewire.hook('morph.updated', () => {
        setTimeout(initSortable, 100);
    });

    $wire.on('open-product-modal', () => {
        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal'));
        modal.show();
    });
    $wire.on('close-product-modal', () => {
        let modal = bootstrap.Modal.getInstance(document.getElementById('productModal'));
        if (modal) modal.hide();
    });
    $wire.on('open-adjustment-modal', () => {
        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustmentModal'));
        modal.show();
    });
    $wire.on('close-adjustment-modal', () => {
        let modal = bootstrap.Modal.getInstance(document.getElementById('adjustmentModal'));
        if (modal) modal.hide();
    });
    $wire.on('open-category-modal', () => {
        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('categoryModal'));
        modal.show();
    });
    $wire.on('close-category-modal', () => {
        let modal = bootstrap.Modal.getInstance(document.getElementById('categoryModal'));
        if (modal) modal.hide();
    });
</script>
@endscript
</div>

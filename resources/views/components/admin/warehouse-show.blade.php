<?php

use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $warehouse;
    public $productSearch = '';
    public $filter_category_id = '';
    public $targetProductId;
    public $adj_qty = 1;
    public $adj_type = 'in'; // 'in' or 'out'
    public $adj_note = '';
    public $current_stock = 0;

    public $activeTab = 'products';

    // History Filters
    public $historyFromDate;
    public $historyToDate;
    public $historyProductId = '';

    // Edit Metadata Fields
    public $name, $location, $notes;

    protected $paginationTheme = 'bootstrap';

    public function mount($id)
    {
        $this->warehouse = Warehouse::findOrFail($id);
        $this->historyFromDate = now()->startOfMonth()->format('Y-m-d');
        $this->historyToDate = now()->endOfMonth()->format('Y-m-d');
        $this->initEditFields();
    }

    protected function initEditFields()
    {
        $this->name = $this->warehouse->name;
        $this->location = $this->warehouse->location;
        $this->notes = $this->warehouse->notes;
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage(); // Reset pagination when switching tabs
    }

    public function updatingHistoryFromDate() { $this->resetPage(); }
    public function updatingHistoryToDate() { $this->resetPage(); }
    public function updatingHistoryProductId() { $this->resetPage(); }

    public function openEditModal()
    {
        $this->initEditFields();
        $this->dispatch('open-edit-warehouse-modal');
    }

    public function saveWarehouse()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $this->warehouse->update([
            'name' => $this->name,
            'location' => $this->location,
            'notes' => $this->notes,
        ]);

        $this->warehouse->refresh();
        session()->flash('success', __('Warehouse updated successfully.'));
        $this->dispatch('close-edit-warehouse-modal');
    }

    public function openAdjustmentModal($productId)
    {
        $this->targetProductId = $productId;
        $product = Product::find($productId);
        $this->current_stock = $product ? $product->stockInWarehouse($this->warehouse->id) : 0;
        $this->adj_qty = 1;
        $this->adj_type = 'in';
        $this->adj_note = '';
        $this->dispatch('open-adjustment-modal');
    }

    public function adjustStock()
    {
        $this->validate([
            'targetProductId' => 'required|exists:products,id',
            'adj_qty' => 'required|numeric|min:0.001',
            'adj_type' => 'required|in:in,out',
        ]);

        StockMovement::create([
            'product_id' => $this->targetProductId,
            'warehouse_id' => $this->warehouse->id,
            'qty_in' => $this->adj_type == 'in' ? $this->adj_qty : 0,
            'qty_out' => $this->adj_type == 'out' ? $this->adj_qty : 0,
            'ref_type' => 'adjustment',
            'note' => $this->adj_note ?: __('Stock Adjustment'),
            'created_by' => Auth::id(),
        ]);

        session()->flash('success', __('Stock adjusted successfully.'));
        $this->dispatch('close-adjustment-modal');
    }

    public function with()
    {
        $productsInWarehouse = Product::whereHas('stockMovements', function($q) {
            $q->where('warehouse_id', $this->warehouse->id);
        })->get();

        $historyQuery = StockMovement::with(['product', 'creator'])
            ->where('warehouse_id', $this->warehouse->id)
            ->whereBetween('created_at', [$this->historyFromDate . ' 00:00:00', $this->historyToDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($this->historyProductId) {
            $historyQuery->where('product_id', $this->historyProductId);
        }

        return [
            'products' => Product::whereHas('stockMovements', function($q) {
                    $q->where('warehouse_id', $this->warehouse->id);
                })
                ->when($this->filter_category_id, function ($q) {
                    $q->where('category_id', $this->filter_category_id);
                })
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->productSearch . '%');
                })
                ->where('is_active', true)
                ->get(),
            'categories' => \App\Models\Category::all(),
            'historyProducts' => $productsInWarehouse,
            'movements' => $historyQuery->paginate(50)
        ];
    }
}; ?>

<div>
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">{{ __('Warehouse Details') }}: {{ $warehouse->name }}</h4>
                    <p class="text-muted mb-0">{{ $warehouse->location }} | {{ $warehouse->notes }}</p>
                </div>
                <div class="d-flex gap-2">
                    <button wire:click="openEditModal" class="btn btn-primary btn-sm">
                        <i class="ri-edit-line align-bottom me-1"></i> {{ __('Edit Warehouse') }}
                    </button>
                    <a href="{{ route('admin.warehouses.index') }}" class="btn btn-secondary btn-sm" wire:navigate>
                        <i class="ri-arrow-go-back-line align-bottom me-1"></i> {{ __('Back to List') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'products' ? 'active' : '' }}" href="#" wire:click.prevent="setTab('products')">
                <i class="ri-box-3-line me-1"></i> {{ __('Products in Warehouse') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'history' ? 'active' : '' }}" href="#" wire:click.prevent="setTab('history')">
                <i class="ri-history-line me-1"></i> {{ __('Stock History') }}
            </a>
        </li>
    </ul>

    @if($activeTab === 'products')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="card-title mb-0">{{ __('Products in Warehouse') }}</h6>
            <div class="d-flex gap-2">
                <select wire:model.live="filter_category_id" class="form-select form-select-sm" style="width: auto;">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <input type="search" wire:model.live="productSearch" class="form-control form-control-sm"
                    placeholder="{{ __('Search Product...') }}" style="width: auto;">
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Current Stock') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php $stock = $product->stockInWarehouse($warehouse->id); @endphp
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>
                                    <span class="badge {{ $stock > $product->stock_alert ? 'bg-success' : 'bg-danger' }}">
                                        {{ number_format($stock, 0) }} {{ $product->unit }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button wire:click="openAdjustmentModal({{ $product->id }})"
                                        class="btn btn-sm btn-soft-primary">
                                        <i class="ri-settings-4-line me-1"></i> {{ __('Adjust Stock') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4">{{ __('No products found in this warehouse.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @elseif($activeTab === 'history')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h6 class="card-title mb-0">{{ __('Warehouse Stock History') }}</h6>
            <div class="d-flex gap-2">
                <select wire:model.live="historyProductId" class="form-select form-select-sm" style="min-width: 150px;">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach($historyProducts as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model.live="historyFromDate" class="form-control form-control-sm">
                <input type="date" wire:model.live="historyToDate" class="form-control form-control-sm">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Note') }}</th>
                            <th>{{ __('Qty In (+)') }}</th>
                            <th>{{ __('Qty Out (-)') }}</th>
                            <th>{{ __('Operator') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr>
                                <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $movement->product->name ?? '---' }}</td>
                                <td>
                                    <span class="badge bg-{{ $movement->qty_in > 0 ? 'success' : 'warning' }}-subtle text-{{ $movement->qty_in > 0 ? 'success' : 'warning' }}">
                                        {{ __($movement->ref_type) }}
                                    </span>
                                </td>
                                <td>{{ $movement->note }}</td>
                                <td class="text-success">{{ $movement->qty_in > 0 ? '+' . number_format($movement->qty_in, 0) : '-' }}</td>
                                <td class="text-danger">{{ $movement->qty_out > 0 ? '-' . number_format($movement->qty_out, 0) : '-' }}</td>
                                <td>{{ $movement->creator->name ?? '---' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">{{ __('No movements found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
    @endif

    <!-- Edit Warehouse Modal -->
    <div wire:ignore.self class="modal fade" id="editWarehouseModal" tabindex="-1"
        aria-labelledby="editWarehouseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editWarehouseModalLabel">{{ __('Edit Warehouse') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="saveWarehouse">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Warehouse Name') }}</label>
                            <input type="text" wire:model="name"
                                class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Location') }}</label>
                            <input type="text" wire:model="location"
                                class="form-control @error('location') is-invalid @enderror">
                            @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea wire:model="notes"
                                class="form-control @error('notes') is-invalid @enderror"></textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
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
                        <div class="alert alert-info py-2 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ __('Current Quantity') }}:</span>
                                <strong>{{ (float) $current_stock }}</strong>
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
                                    {{ (float) ($adj_type == 'in' ? $current_stock + (float) ($adj_qty ?: 0) : $current_stock - (float) ($adj_qty ?: 0)) }}
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
                            <input type="number" step="0.001" wire:model.live="adj_qty" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Note') }}</label>
                            <textarea wire:model="adj_note" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Adjust Stock') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@script
<script>
    $wire.on('open-edit-warehouse-modal', () => {
        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editWarehouseModal'));
        modal.show();
    });
    $wire.on('close-edit-warehouse-modal', () => {
        let modal = bootstrap.Modal.getInstance(document.getElementById('editWarehouseModal'));
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
</script>
@endscript
</div>
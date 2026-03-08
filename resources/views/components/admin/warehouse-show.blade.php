<?php

use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $warehouse;
    public $productSearch = '';
    public $targetProductId;
    public $adj_qty = 1;
    public $adj_type = 'in'; // 'in' or 'out'
    public $adj_note = '';

    public function mount($id)
    {
        $this->warehouse = Warehouse::findOrFail($id);
    }

    public function openAdjustmentModal($productId)
    {
        $this->targetProductId = $productId;
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
        return [
            'products' => Product::where(function ($q) {
                $q->where('name', 'like', '%' . $this->productSearch . '%')
                    ->orWhere('sku', 'like', '%' . $this->productSearch . '%');
            })
                ->whereHas('stockMovements', function ($q) {
                    $q->where('warehouse_id', $this->warehouse->id);
                })
                ->get()
        ];
    }
}; ?>

<div>
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-0">{{ __('Warehouse Details') }}: {{ $warehouse->name }}</h4>
                    <p class="text-muted mb-0">{{ $warehouse->location }} | {{ $warehouse->notes }}</p>
                </div>
                <a href="{{ route('admin.warehouses.index') }}" class="btn btn-secondary btn-sm" wire:navigate>
                    <i class="ri-arrow-go-back-line align-bottom me-1"></i> {{ __('Back to List') }}
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">{{ __('Products in Warehouse') }}</h6>
            <div class="w-25">
                <input type="search" wire:model.live="productSearch" class="form-control form-control-sm"
                    placeholder="{{ __('Search Product...') }}">
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
                            <th>{{ __('SKU') }}</th>
                            <th>{{ __('Current Stock') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php $stock = $product->stockInWarehouse($warehouse->id); @endphp
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->sku }}</td>
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
                                <td colspan="4" class="text-center py-4">{{ __('No products found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                            <label class="form-label">{{ __('Adjustment Type') }}</label>
                            <select wire:model="adj_type" class="form-select">
                                <option value="in">{{ __('Adjustment In') }} (+)</option>
                                <option value="out">{{ __('Adjustment Out') }} (-)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Adjustment Qty') }}</label>
                            <input type="number" step="0.001" wire:model="adj_qty" class="form-control">
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

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-adjustment-modal', () => {
                new bootstrap.Modal(document.getElementById('adjustmentModal')).show();
            });
            Livewire.on('close-adjustment-modal', () => {
                var modal = bootstrap.Modal.getInstance(document.getElementById('adjustmentModal'));
                if (modal) modal.hide();
            });
        });
    </script>
</div>
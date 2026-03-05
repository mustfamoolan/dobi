<?php

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $productId, $fromWarehouseId, $toWarehouseId, $qty, $note;
    public $products, $warehouses;

    public function mount()
    {
        $this->products = Product::where('is_active', true)->get();
        $this->warehouses = Warehouse::where('is_active', true)->get();
    }

    public function transfer()
    {
        $this->validate([
            'productId' => 'required|exists:products,id',
            'fromWarehouseId' => 'required|exists:warehouses,id|different:toWarehouseId',
            'toWarehouseId' => 'required|exists:warehouses,id',
            'qty' => 'required|numeric|min:0.001',
            'note' => 'nullable|string',
        ]);

        $product = Product::findOrFail($this->productId);

        // Check availability in source warehouse
        $currentStockInSource = StockMovement::where('product_id', $this->productId)
            ->where('warehouse_id', $this->fromWarehouseId)
            ->sum('qty_in') - StockMovement::where('product_id', $this->productId)
                ->where('warehouse_id', $this->fromWarehouseId)
                ->sum('qty_out');

        if ($currentStockInSource < $this->qty) {
            session()->flash('error', __('Insufficient stock in source warehouse. Current stock: ') . number_format($currentStockInSource, 2));
            return;
        }

        // 1. Qty Out from source
        StockMovement::create([
            'product_id' => $this->productId,
            'warehouse_id' => $this->fromWarehouseId,
            'qty_in' => 0,
            'qty_out' => $this->qty,
            'ref_type' => 'transfer_out',
            'note' => $this->note ?? __('Transfer to ') . Warehouse::find($this->toWarehouseId)->name,
            'created_by' => Auth::id(),
        ]);

        // 2. Qty In to destination
        StockMovement::create([
            'product_id' => $this->productId,
            'warehouse_id' => $this->toWarehouseId,
            'qty_in' => $this->qty,
            'qty_out' => 0,
            'ref_type' => 'transfer_in',
            'note' => $this->note ?? __('Transfer from ') . Warehouse::find($this->fromWarehouseId)->name,
            'created_by' => Auth::id(),
        ]);

        $this->reset(['productId', 'fromWarehouseId', 'toWarehouseId', 'qty', 'note']);
        session()->flash('success', __('Stock transferred successfully.'));
    }

    public function render()
    {
        return view('components.admin.stock-transfer');
    }
};
?>

<div>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('Stock Transfer Between Warehouses') }}</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form wire:submit.prevent="transfer">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Select Product') }}</label>
                        <select wire:model="productId" class="form-select @error('productId') is-invalid @enderror">
                            <option value="">-- {{ __('Select Product') }} --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                        @error('productId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Quantity') }}</label>
                        <input type="number" step="0.001" wire:model="qty"
                            class="form-control @error('qty') is-invalid @enderror">
                        @error('qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('From Warehouse (Source)') }}</label>
                        <select wire:model="fromWarehouseId"
                            class="form-select @error('fromWarehouseId') is-invalid @enderror">
                            <option value="">-- {{ __('Select Warehouse') }} --</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('fromWarehouseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('To Warehouse (Destination)') }}</label>
                        <select wire:model="toWarehouseId"
                            class="form-select @error('toWarehouseId') is-invalid @enderror">
                            <option value="">-- {{ __('Select Warehouse') }} --</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('toWarehouseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Note') }}</label>
                    <textarea wire:model="note" class="form-control" rows="2"></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-arrow-left-right-line align-bottom me-1"></i> {{ __('Start Transfer') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
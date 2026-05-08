<?php

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    public $productId, $fromWarehouseId, $toWarehouseId, $qty, $note;
    public $products, $warehouses;
    public $warehouseStocks = [];

    public function mount()
    {
        $this->products = Product::where('is_active', true)->get();
        $this->warehouses = Warehouse::where('is_active', true)->get();
        if ($this->productId) {
            $this->loadWarehouseStocks();
        }
    }

    public function updatedProductId($value)
    {
        $this->loadWarehouseStocks();
    }

    protected function loadWarehouseStocks()
    {
        if ($this->productId) {
            $stocks = StockMovement::query()
                ->select('warehouse_id')
                ->selectRaw('SUM(qty_in) - SUM(qty_out) as total')
                ->where('product_id', $this->productId)
                ->groupBy('warehouse_id')
                ->get();
            
            $this->warehouseStocks = [];
            foreach ($stocks as $stock) {
                $this->warehouseStocks[(int)$stock->warehouse_id] = (float)$stock->total;
            }
        } else {
            $this->warehouseStocks = [];
        }
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

        // Check availability in source warehouse using model method
        $currentStockInSource = $product->currentStock($this->fromWarehouseId);

        if ($currentStockInSource < $this->qty) {
            session()->flash('error', __('Insufficient stock in source warehouse. Current stock: ') . number_format($currentStockInSource, 2));
            return;
        }

        DB::transaction(function () use ($product) {
            $refId = 'TRF-' . strtoupper(Str::random(8));
            $fromWarehouse = Warehouse::find($this->fromWarehouseId);
            $toWarehouse = Warehouse::find($this->toWarehouseId);

            // 1. Qty Out from source
            StockMovement::create([
                'product_id' => $this->productId,
                'warehouse_id' => $this->fromWarehouseId,
                'qty_in' => 0,
                'qty_out' => $this->qty,
                'ref_type' => 'transfer_out',
                'ref_id' => $refId,
                'note' => $this->note ?? __('Transfer to ') . $toWarehouse->name,
                'created_by' => Auth::id(),
            ]);

            // 2. Qty In to destination
            StockMovement::create([
                'product_id' => $this->productId,
                'warehouse_id' => $this->toWarehouseId,
                'qty_in' => $this->qty,
                'qty_out' => 0,
                'ref_type' => 'transfer_in',
                'ref_id' => $refId,
                'note' => $this->note ?? __('Transfer from ') . $fromWarehouse->name,
                'created_by' => Auth::id(),
            ]);

            // Log activity
            \App\Services\ActivityLogger::log(
                'transferred',
                __('Transferred :qty :unit of :product from :from to :to', [
                    'qty' => $this->qty,
                    'unit' => $product->unit,
                    'product' => $product->name,
                    'from' => $fromWarehouse->name,
                    'to' => $toWarehouse->name
                ]),
                $product,
                ['ref_id' => $refId]
            );
        });

        $this->reset(['productId', 'fromWarehouseId', 'toWarehouseId', 'qty', 'note', 'warehouseStocks']);
        session()->flash('success', __('Stock transferred successfully.'));
    }

    public function getRecentTransfersProperty()
    {
        return StockMovement::with(['product', 'warehouse', 'creator'])
            ->whereIn('ref_type', ['transfer_in', 'transfer_out'])
            ->latest()
            ->take(20)
            ->get();
    }

    public function render()
    {
        return view('components.admin.stock-transfer');
    }
};
?>

<div>
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Start Stock Transfer') }}</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form wire:submit.prevent="transfer">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Select Product') }}</label>
                            <select wire:model.live="productId" class="form-select @error('productId') is-invalid @enderror">
                                <option value="">-- {{ __('Select Product') }} --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->unit }})</option>
                                @endforeach
                            </select>
                            @error('productId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('From Warehouse (Source)') }}</label>
                            <select wire:model="fromWarehouseId"
                                class="form-select @error('fromWarehouseId') is-invalid @enderror">
                                <option value="">-- {{ __('Select Warehouse') }} --</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">
                                        {{ $warehouse->name }} 
                                        @if(isset($warehouseStocks[$warehouse->id]))
                                            ({{ number_format($warehouseStocks[$warehouse->id], 2) }})
                                        @else
                                            (0)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('fromWarehouseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('To Warehouse (Destination)') }}</label>
                            <select wire:model="toWarehouseId"
                                class="form-select @error('toWarehouseId') is-invalid @enderror">
                                <option value="">-- {{ __('Select Warehouse') }} --</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">
                                        {{ $warehouse->name }}
                                        @if(isset($warehouseStocks[$warehouse->id]))
                                            ({{ number_format($warehouseStocks[$warehouse->id], 2) }})
                                        @else
                                            (0)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('toWarehouseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Quantity') }}</label>
                            <input type="number" step="0.001" wire:model="qty"
                                class="form-control @error('qty') is-invalid @enderror">
                            @error('qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Note') }}</label>
                            <textarea wire:model="note" class="form-control" rows="2" placeholder="{{ __('Optional note...') }}"></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ri-arrow-left-right-line align-bottom me-1"></i> {{ __('Execute Transfer') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Recent Transfers History') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Qty') }}</th>
                                    <th>{{ __('Operator') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->recentTransfers as $movement)
                                    <tr>
                                        <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                        <td>{{ $movement->product->name }}</td>
                                        <td>{{ $movement->warehouse->name }}</td>
                                        <td>
                                            @if($movement->ref_type == 'transfer_in')
                                                <span class="badge bg-success-subtle text-success">{{ __('IN') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">{{ __('OUT') }}</span>
                                            @endif
                                        </td>
                                        <td class="fw-bold">{{ number_format($movement->qty_in + $movement->qty_out, 2) }}</td>
                                        <td>{{ $movement->creator->name ?? '---' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-3 text-muted">{{ __('No recent transfers found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
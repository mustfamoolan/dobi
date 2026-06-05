<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $productId;
    public $fromDate;
    public $toDate;
    public $warehouseId = '';

    protected $paginationTheme = 'bootstrap';

    public function mount($productId)
    {
        $this->productId = $productId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingFromDate() { $this->resetPage(); }
    public function updatingToDate() { $this->resetPage(); }
    public function updatingWarehouseId() { $this->resetPage(); }

    public function render(): mixed
    {
        $product = Product::findOrFail($this->productId);
        $warehouses = Warehouse::all();

        // 1. Balance before fromDate
        $baseQuery = StockMovement::where('product_id', $this->productId)
            ->where('created_at', '<', $this->fromDate . ' 00:00:00');
            
        if ($this->warehouseId) {
            $baseQuery->where('warehouse_id', $this->warehouseId);
        }

        $qtyIn = (clone $baseQuery)->sum('qty_in');
        $qtyOut = (clone $baseQuery)->sum('qty_out');
        $balanceForward = $qtyIn - $qtyOut;

        // Warehouse specific balances before fromDate
        $warehouseBalances = [];
        if (empty($this->warehouseId)) {
            $whBalancesQuery = StockMovement::where('product_id', $this->productId)
                ->where('created_at', '<', $this->fromDate . ' 00:00:00')
                ->selectRaw('warehouse_id, SUM(qty_in) as total_in, SUM(qty_out) as total_out')
                ->groupBy('warehouse_id')
                ->get();
            foreach ($whBalancesQuery as $wb) {
                $warehouseBalances[$wb->warehouse_id] = $wb->total_in - $wb->total_out;
            }
        }

        // 2. Pagination
        $query = StockMovement::with(['creator', 'warehouse'])
            ->where('product_id', $this->productId)
            ->whereBetween('created_at', [$this->fromDate . ' 00:00:00', $this->toDate . ' 23:59:59'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if ($this->warehouseId) {
            $query->where('warehouse_id', $this->warehouseId);
        }

        $movements = $query->paginate(50);

        // 3. Add previous pages' movements in range to the forward balances
        $page = $this->getPage();
        if ($page > 1) {
            $previousMovementsQuery = StockMovement::where('product_id', $this->productId)
                ->whereBetween('created_at', [$this->fromDate . ' 00:00:00', $this->toDate . ' 23:59:59'])
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc');
            if ($this->warehouseId) {
                $previousMovementsQuery->where('warehouse_id', $this->warehouseId);
            }
            $previousMovements = $previousMovementsQuery->limit(($page - 1) * 50)->get();

            foreach ($previousMovements as $pm) {
                $balanceForward += $pm->qty_in - $pm->qty_out;
                
                if (empty($this->warehouseId)) {
                    if (!isset($warehouseBalances[$pm->warehouse_id])) {
                        $warehouseBalances[$pm->warehouse_id] = 0;
                    }
                    $warehouseBalances[$pm->warehouse_id] += $pm->qty_in - $pm->qty_out;
                }
            }
        }

        // Assign running warehouse balances directly into the paginated items
        if (empty($this->warehouseId)) {
            foreach ($movements as $movement) {
                if (!isset($warehouseBalances[$movement->warehouse_id])) {
                    $warehouseBalances[$movement->warehouse_id] = 0;
                }
                $warehouseBalances[$movement->warehouse_id] += $movement->qty_in - $movement->qty_out;
                $movement->warehouse_running_balance = $warehouseBalances[$movement->warehouse_id];
            }
        }

        return view('components.admin.product-stock-history', [
            'product' => $product,
            'warehouses' => $warehouses,
            'movements' => $movements,
            'balanceForward' => $balanceForward
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Stock History') }}: {{ $product->name }}</h5>
            <div class="d-flex gap-2">
                <select wire:model.live="warehouseId" class="form-select form-select-sm" style="min-width: 150px;">
                    <option value="">{{ __('All Warehouses') }}</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Warehouse') }}</th>
                            <th>{{ __('Note') }}</th>
                            <th>{{ __('Qty In (+)') }}</th>
                            <th>{{ __('Qty Out (-)') }}</th>
                            @if(empty($warehouseId))
                            <th>{{ __('Wh. Balance') }}</th>
                            <th>{{ __('Total Balance') }}</th>
                            @else
                            <th>{{ __('Balance') }}</th>
                            @endif
                            <th>{{ __('Operator') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-info">
                            <td colspan="6"><strong>{{ __('Balance Forward') }}</strong></td>
                            <td colspan="{{ empty($warehouseId) ? 3 : 2 }}"><strong>{{ number_format($balanceForward, 0) }}</strong></td>
                        </tr>
                        @php $runningBalance = $balanceForward; @endphp
                        @foreach($movements as $movement)
                            @php 
                                $runningBalance += $movement->qty_in; 
                                $runningBalance -= $movement->qty_out;
                            @endphp
                            <tr>
                                <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge bg-{{ $movement->qty_in > 0 ? 'success' : 'warning' }}-subtle text-{{ $movement->qty_in > 0 ? 'success' : 'warning' }}">
                                        {{ __($movement->ref_type) }}
                                    </span>
                                </td>
                                <td>{{ $movement->warehouse->name ?? '---' }}</td>
                                <td>{{ $movement->note }}</td>
                                <td class="text-success">{{ $movement->qty_in > 0 ? '+' . number_format($movement->qty_in, 0) : '-' }}</td>
                                <td class="text-danger">{{ $movement->qty_out > 0 ? '-' . number_format($movement->qty_out, 0) : '-' }}</td>
                                @if(empty($warehouseId))
                                <td><strong class="text-primary">{{ number_format($movement->warehouse_running_balance, 0) }}</strong></td>
                                <td><strong>{{ number_format($runningBalance, 0) }}</strong></td>
                                @else
                                <td><strong>{{ number_format($runningBalance, 0) }}</strong></td>
                                @endif
                                <td>{{ $movement->creator->name ?? '---' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="6" class="text-end">{{ __('Current Stock') }}</th>
                            <th colspan="{{ empty($warehouseId) ? 3 : 2 }}">{{ number_format($runningBalance, 0) }} {{ $product->unit }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
</div>


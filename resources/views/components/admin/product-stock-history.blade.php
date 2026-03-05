<?php

use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $productId;
    public $fromDate;
    public $toDate;

    protected $paginationTheme = 'bootstrap';

    public function mount($productId)
    {
        $this->productId = $productId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingFromDate() { $this->resetPage(); }
    public function updatingToDate() { $this->resetPage(); }

    public function render(): mixed
    {
        $product = Product::findOrFail($this->productId);

        $query = StockMovement::where('product_id', $this->productId)
            ->whereBetween('created_at', [$this->fromDate . ' 00:00:00', $this->toDate . ' 23:59:59'])
            ->orderBy('created_at', 'asc');

        $movements = $query->paginate(50);

        // Calculate Balance Forward (stock before fromDate)
        $qtyIn = StockMovement::where('product_id', $this->productId)
            ->where('created_at', '<', $this->fromDate . ' 00:00:00')
            ->sum('qty_in');
        $qtyOut = StockMovement::where('product_id', $this->productId)
            ->where('created_at', '<', $this->fromDate . ' 00:00:00')
            ->sum('qty_out');
        
        $balanceForward = $qtyIn - $qtyOut;

        return view('components.admin.product-stock-history', [
            'product' => $product,
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
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Qty In (+)') }}</th>
                            <th>{{ __('Qty Out (-)') }}</th>
                            <th>{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-info">
                            <td colspan="5"><strong>{{ __('Balance Forward') }}</strong></td>
                            <td colspan="1"><strong>{{ number_format($balanceForward, 0) }}</strong></td>
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
                                <td>{{ $movement->note }}</td>
                                <td class="text-success">{{ $movement->qty_in > 0 ? '+' . number_format($movement->qty_in, 0) : '-' }}</td>
                                <td class="text-danger">{{ $movement->qty_out > 0 ? '-' . number_format($movement->qty_out, 0) : '-' }}</td>
                                <td><strong>{{ number_format($runningBalance, 0) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">{{ __('Current Stock') }}</th>
                            <th>{{ number_format($runningBalance, 0) }} {{ $product->unit }}</th>
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

<?php

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $supplierId;
    public $fromDate;
    public $toDate;

    protected $paginationTheme = 'bootstrap';

    public function mount($supplierId)
    {
        $this->supplierId = $supplierId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function render(): mixed
    {
        $supplier = Supplier::findOrFail($this->supplierId);

        $query = SupplierLedger::where('supplier_id', $this->supplierId)
            ->whereBetween('date', [$this->fromDate, $this->toDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        $entries = $query->paginate(50);

        // Calculate Opening Balance before the "from" date
        // Note: For suppliers, Credit is what we owe, Debit is what we pay.
        $previousBalance = SupplierLedger::where('supplier_id', $this->supplierId)
            ->where('date', '<', $this->fromDate)
            ->selectRaw('SUM(credit) - SUM(debit) as balance')
            ->first()->balance ?? 0;

        return view('components.admin.supplier-ledger', [
            'supplier' => $supplier,
            'entries' => $entries,
            'previousBalance' => $previousBalance
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0">{{ __('Supplier Statement') }}: {{ $supplier->name }}</h5>
                <p class="text-muted mb-0">{{ $supplier->phone }}</p>
            </div>
            <div class="d-flex gap-2">
                <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
                <button onclick="window.print()" class="btn btn-soft-secondary btn-sm"><i class="ri-printer-line"></i>
                    {{ __('Print') }}</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('Credit (+) We Owe') }}</th>
                            <th class="text-end">{{ __('Debit (-) Payments') }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-info">
                            <td colspan="2"><strong>{{ __('Balance Forward (Before') }} {{ $fromDate }})</strong></td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                            <td class="text-end"><strong>{{ number_format($previousBalance, 0) }}</strong></td>
                        </tr>
                        @php $currentBalance = $previousBalance; @endphp
                        @foreach($entries as $entry)
                            @php $currentBalance += ($entry->credit - $entry->debit); @endphp
                            <tr>
                                <td>{{ $entry->date }}</td>
                                <td>
                                    {{ $entry->description }}
                                    @if($entry->ref_type)
                                        <span class="badge bg-light text-dark ms-1">#{{ $entry->ref_id }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-success">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 0) : '-' }}
                                    @if($entry->currency && $entry->currency !== 'IQD')
                                        <br><small class="text-muted">{{ number_format($entry->credit, 0) }}
                                            {{ $entry->currency }}</small>
                                    @endif
                                </td>
                                <td class="text-end text-danger">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 0) : '-' }}
                                    @if($entry->currency && $entry->currency !== 'IQD')
                                        <br><small class="text-muted">{{ number_format($entry->debit, 0) }}
                                            {{ $entry->currency }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <strong>{{ number_format($currentBalance, 0) }}</strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-end">
                <div class="fs-16 px-4 py-2 bg-light d-inline-block rounded">
                    <strong>{{ __('Net Balance') }}: {{ number_format($currentBalance, 0) }} IQD</strong>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

use App\Models\Customer;
use App\Models\CustomerLedger;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $customerId;
    public $fromDate;
    public $toDate;
    public $currency = 'IQD';

    protected $paginationTheme = 'bootstrap';

    public function mount($customerId)
    {
        $this->customerId = $customerId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function render(): mixed
    {
        $customer = Customer::findOrFail($this->customerId);

        $query = CustomerLedger::where('customer_id', $this->customerId)
            ->where('currency', $this->currency)
            ->whereBetween('date', [$this->fromDate, $this->toDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        $entries = $query->paginate(50);

        // Calculate Opening Balance before the "from" date
        $previousBalance = CustomerLedger::where('customer_id', $this->customerId)
            ->where('currency', $this->currency)
            ->where('date', '<', $this->fromDate)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;

        // Add the customer's initial opening balance for this currency if it's the very first entry
        // and its date is within the range or if we are looking at the start of time.
        // Actually, the saving logic already creates a ledger entry for 'opening_balance'. 
        // So we just need to ensure the ledger entry is picked up.

        return view('components.admin.customer-ledger', [
            'customer' => $customer,
            'entries' => $entries,
            'previousBalance' => $previousBalance
        ]);
    }
};
?>

<div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">{{ __('Statement of Account') }}: {{ $customer->name }}</h5>
                        <p class="text-muted mb-0">{{ $customer->phone }} |
                            {{ __('Balance IQD') }}: {{ number_format($customer->opening_balance_iqd, 0) }} |
                            {{ __('Balance USD') }}: {{ number_format($customer->opening_balance_usd, 2) }}
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <select wire:model.live="currency" class="form-select form-select-sm" style="width: 100px;">
                            <option value="IQD">IQD</option>
                            <option value="USD">USD</option>
                        </select>
                        <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                        <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
                        <button onclick="window.print()" class="btn btn-soft-secondary btn-sm"><i
                                class="ri-printer-line"></i> {{ __('Print') }}</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th class="text-end">{{ __('Debit (+)') }}</th>
                                    <th class="text-end">{{ __('Credit (-)') }}</th>
                                    <th class="text-end">{{ __('Balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="table-info">
                                    <td colspan="2"><strong>{{ __('Balance Forward (Before') }}
                                            {{ $fromDate }})</strong></td>
                                    <td class="text-end">-</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end"><strong>{{ number_format($previousBalance, 0) }}</strong></td>
                                </tr>
                                @php $currentBalance = $previousBalance; @endphp
                                @foreach($entries as $entry)
                                    @php $currentBalance += ($entry->debit - $entry->credit); @endphp
                                    <tr>
                                        <td>{{ $entry->date }}</td>
                                        <td>
                                            {{ $entry->description }}
                                            @if($entry->ref_id)
                                                <span class="badge bg-light text-dark ms-1">#{{ $entry->ref_id }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end {{ $entry->debit > 0 ? 'text-danger' : '' }}">
                                            @if($entry->debit > 0)
                                                {{ number_format($entry->debit, 0) }}
                                                @if($entry->currency && $entry->currency !== 'IQD')
                                                    <br><small class="text-muted">{{ number_format($entry->debit, 0) }}
                                                        {{ $entry->currency }}</small>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end {{ $entry->credit > 0 ? 'text-success' : '' }}">
                                            @if($entry->credit > 0)
                                                {{ number_format($entry->credit, 0) }}
                                                @if($entry->currency && $entry->currency !== 'IQD')
                                                    <br><small class="text-muted">{{ number_format($entry->credit, 0) }}
                                                        {{ $entry->currency }}</small>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end"><strong>{{ number_format($currentBalance, 0) }} </strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">{{ __('Closing Balance') }}</th>
                                    <th class="text-end">{{ number_format($entries->sum('debit'), 0) }}</th>
                                    <th class="text-end">{{ number_format($entries->sum('credit'), 0) }}</th>
                                    <th class="text-end bg-primary-subtle">
                                        <strong>{{ number_format($currentBalance, $currency === 'USD' ? 2 : 0) }}
                                            {{ $currency }}</strong>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $entries->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
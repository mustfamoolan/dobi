<?php

use App\Models\FinancialAccount;
use App\Models\AccountLedger;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $accountId;
    public $fromDate;
    public $toDate;

    protected $paginationTheme = 'bootstrap';

    public function mount($accountId)
    {
        $this->accountId = $accountId;
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function with()
    {
        $account = FinancialAccount::findOrFail($this->accountId);

        $query = AccountLedger::where('account_id', $this->accountId)
            ->whereBetween('date', [$this->fromDate, $this->toDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        $entries = $query->paginate(50);

        // Calculate Opening Balance before the "from" date
        $previousBalance = AccountLedger::where('account_id', $this->accountId)
            ->where('date', '<', $this->fromDate)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;

        return [
            'account' => $account,
            'entries' => $entries,
            'previousBalance' => $previousBalance
        ];
    }
}; ?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0">{{ __('Treasury Statement') }}: {{ $account->name }}</h5>
                <p class="text-muted mb-0">{{ $account->type == 'cash' ? __('Cash') : __('Bank') }} |
                    {{ __('Balance') }}:
                    <strong>{{ number_format($account->current_balance, 0) }} {{ $account->currency }}</strong>
                </p>
            </div>
            <div class="d-flex gap-2">
                <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
                <button onclick="window.print()" class="btn btn-soft-secondary btn-sm">
                    <i class="ri-printer-line"></i> {{ __('Print') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('In (+)') }}</th>
                            <th class="text-end">{{ __('Out (-)') }}</th>
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
                            @php $currentBalance += ($entry->debit - $entry->credit); @endphp
                            <tr>
                                <td>{{ $entry->date }}</td>
                                <td>
                                    {{ $entry->description }}
                                    @if($entry->ref_id)
                                        <span class="badge bg-light text-dark ms-1">#{{ $entry->ref_id }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-success">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 0) : '-' }}
                                </td>
                                <td class="text-end text-danger">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 0) : '-' }}
                                </td>
                                <td class="text-end">
                                    <strong>{{ number_format($currentBalance, 0) }}</strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">{{ __('Closing Balance') }}</th>
                            <th class="text-end">{{ number_format($entries->sum('debit'), 0) }}</th>
                            <th class="text-end">{{ number_format($entries->sum('credit'), 0) }}</th>
                            <th class="text-end bg-primary-subtle">
                                <strong>{{ number_format($currentBalance, 0) }} {{ $account->currency }}</strong>
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
<?php

use App\Models\FinancialAccount;
use App\Models\AccountLedger;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $accountId;
    public $name, $type = 'cash', $currency = 'IQD', $opening_balance = 0, $is_active = true;
    public $isEditMode = false;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['name', 'type', 'currency', 'opening_balance', 'is_active', 'accountId', 'isEditMode']);
        $this->dispatch('open-account-modal');
    }

    public function edit($id)
    {
        $account = FinancialAccount::findOrFail($id);
        $this->accountId = $account->id;
        $this->name = $account->name;
        $this->type = $account->type;
        $this->currency = $account->currency;
        $this->opening_balance = $account->opening_balance;
        $this->is_active = $account->is_active;
        $this->isEditMode = true;
        $this->dispatch('open-account-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'currency' => 'required|string|max:10',
            'opening_balance' => 'required|numeric',
        ]);

        DB::transaction(function () {
            if ($this->isEditMode) {
                $account = FinancialAccount::findOrFail($this->accountId);

                // Update basic info
                $account->update([
                    'name' => $this->name,
                    'type' => $this->type,
                    'currency' => $this->currency,
                    'is_active' => $this->is_active,
                ]);

                // Opening balance update logic is complex, for simplicity we only allow it on creation
                // and view it as read-only or handled via adjustments.
                session()->flash('success', __('Account updated successfully.'));
            } else {
                $account = FinancialAccount::create([
                    'name' => $this->name,
                    'type' => $this->type,
                    'currency' => $this->currency,
                    'opening_balance' => $this->opening_balance,
                    'current_balance' => $this->opening_balance,
                    'is_active' => $this->is_active,
                    'created_by' => Auth::id(),
                ]);

                if ($this->opening_balance != 0) {
                    AccountLedger::create([
                        'account_id' => $account->id,
                        'date' => now()->format('Y-m-d'),
                        'description' => __('Opening Balance'),
                        'debit' => $this->opening_balance > 0 ? $this->opening_balance : 0,
                        'credit' => $this->opening_balance < 0 ? abs($this->opening_balance) : 0,
                        'balance' => $this->opening_balance,
                        'ref_type' => 'opening',
                        'created_by' => Auth::id(),
                    ]);
                }

                session()->flash('success', __('Financial Account created successfully.'));
            }
        });

        $this->dispatch('close-account-modal');
    }

    public function delete($id)
    {
        $account = FinancialAccount::findOrFail($id);

        // Check if account has transactions beyond opening
        if ($account->ledgerEntries()->where('ref_type', '!=', 'opening')->count() > 0) {
            session()->flash('error', __('Cannot delete account with existing transactions.'));
            return;
        }

        $account->delete();
        session()->flash('success', __('Account deleted successfully.'));
    }

    public function with()
    {
        return [
            'accounts' => FinancialAccount::where('name', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Financial Accounts & Treasuries') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Accounts...') }}">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Account') }}
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

            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Currency') }}</th>
                            <th class="text-end">{{ __('Current Balance') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accounts as $account)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm flex-shrink-0 me-3">
                                            <div class="avatar-title bg-light rounded text-primary fs-20">
                                                <i
                                                    class="{{ $account->type == 'cash' ? 'ri-safe-2-line' : 'ri-bank-line' }}"></i>
                                            </div>
                                        </div>
                                        <h6 class="fs-14 mb-0">{{ $account->name }}</h6>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="badge {{ $account->type == 'cash' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }}">
                                        {{ $account->type == 'cash' ? __('Cash') : __('Bank') }}
                                    </span>
                                </td>
                                <td>{{ $account->currency }}</td>
                                <td class="text-end">
                                    <strong class="{{ $account->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($account->current_balance, 0) }}
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge {{ $account->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $account->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.accounts.ledger', $account->id) }}"
                                        class="btn btn-sm btn-soft-warning" title="{{ __('Ledger') }}">
                                        <i class="ri-history-line"></i>
                                    </a>
                                    <button wire:click="edit({{ $account->id }})" class="btn btn-sm btn-soft-info"
                                        title="{{ __('Edit') }}">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button wire:click="delete({{ $account->id }})"
                                        onclick="return confirm('{{ __('Are you sure?') }}')"
                                        class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $accounts->links() }}
            </div>
        </div>
    </div>

    <!-- Account Modal -->
    <div wire:ignore.self class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountModalLabel">
                        {{ $isEditMode ? __('Edit Account') : __('Add New Account') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Account Name') }}</label>
                                <input type="text" wire:model="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    placeholder="{{ __('e.g. Main Cashbox, Bank Account...') }}">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                    <option value="cash">{{ __('Cash / Treasury') }}</option>
                                    <option value="bank">{{ __('Bank') }}</option>
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Currency') }}</label>
                                <select wire:model="currency"
                                    class="form-select @error('currency') is-invalid @enderror">
                                    <option value="IQD">IQD</option>
                                    <option value="USD">USD</option>
                                </select>
                                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if(!$isEditMode)
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">{{ __('Opening Balance') }}</label>
                                    <input type="number" step="0.01" wire:model="opening_balance"
                                        class="form-control @error('opening_balance') is-invalid @enderror">
                                    @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <small
                                        class="text-muted">{{ __('Positive for current balance, negative for deficit.') }}</small>
                                </div>
                            @endif

                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="is_active">
                                    <label class="form-check-label">{{ __('Account is Active') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit"
                            class="btn btn-primary">{{ $isEditMode ? __('Update') : __('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-account-modal', () => {
                var myModal = new bootstrap.Modal(document.getElementById('accountModal'));
                myModal.show();
            });
            Livewire.on('close-account-modal', () => {
                var myModalEl = document.getElementById('accountModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
</div>
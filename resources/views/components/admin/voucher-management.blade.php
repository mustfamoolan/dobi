<?php

use App\Models\Voucher;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Employee;
use App\Models\CustomerLedger;
use App\Models\SupplierLedger;
use App\Models\EmployeeLedger;
use App\Models\AppSetting;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;

    // Voucher Form Fields
    public $date;
    public $type = 'receipt'; // receipt, payment
    public $account_type = 'customer'; // customer, supplier, employee
    public $account_id;
    public $amount;
    public $currency = 'USD';
    public $exchange_rate;
    public $notes;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->date = now()->format('Y-m-d');
        $setting = AppSetting::first();
        $this->exchange_rate = $setting->exchange_rate ?? 1500;
    }

    public function openCreateModal($type = 'receipt')
    {
        $this->reset(['account_id', 'amount', 'notes']);
        $this->type = $type;
        $this->date = now()->format('Y-m-d');
        $setting = AppSetting::first();
        $this->exchange_rate = $setting->exchange_rate ?? 1500;
        $this->dispatch('open-voucher-modal');
    }

    public function save()
    {
        $this->validate([
            'date' => 'required|date',
            'type' => 'required|in:receipt,payment',
            'account_type' => 'required|in:customer,supplier,employee',
            'account_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string',
            'exchange_rate' => 'required|numeric|min:1',
        ]);

        DB::transaction(function () {
            // 1. Create Voucher record
            $voucher = Voucher::create([
                'date' => $this->date,
                'type' => $this->type,
                'account_type' => $this->account_type,
                'account_id' => $this->account_id,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'exchange_rate' => $this->exchange_rate,
                'notes' => $this->notes,
                'created_by' => Auth::id(),
            ]);

            // 2. Update corresponding Ledger
            $description = ucfirst($this->type) . ' Voucher #' . $voucher->id . ($this->notes ? ': ' . $this->notes : '');

            if ($this->account_type === 'customer') {
                CustomerLedger::create([
                    'customer_id' => $this->account_id,
                    'date' => $this->date,
                    'type' => $this->type,
                    'description' => $description,
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'debit' => $this->type === 'payment' ? $this->amount : 0, // payment to customer (rare)
                    'credit' => $this->type === 'receipt' ? $this->amount : 0, // receipt from customer
                    'balance' => 0, // Balance logic handled in view or by observer
                    'ref_type' => 'voucher',
                    'ref_id' => $voucher->id,
                    'created_by' => Auth::id(),
                ]);
            } elseif ($this->account_type === 'supplier') {
                SupplierLedger::create([
                    'supplier_id' => $this->account_id,
                    'date' => $this->date,
                    'type' => $this->type,
                    'description' => $description,
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'debit' => $this->type === 'payment' ? $this->amount : 0, // payment to supplier
                    'credit' => $this->type === 'receipt' ? $this->amount : 0, // receipt from supplier (rare)
                    'balance' => 0,
                    'ref_type' => 'voucher',
                    'ref_id' => $voucher->id,
                    'created_by' => Auth::id(),
                ]);
            } elseif ($this->account_type === 'employee') {
                EmployeeLedger::create([
                    'employee_id' => $this->account_id,
                    'date' => $this->date,
                    'type' => $this->type === 'receipt' ? 'repayment' : 'payment',
                    'description' => $description,
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'debit' => $this->type === 'payment' ? $this->amount : 0, // payment to employee (salary/advance)
                    'credit' => $this->type === 'receipt' ? $this->amount : 0, // receipt from employee
                    'balance' => 0,
                    'ref_type' => 'voucher',
                    'ref_id' => $voucher->id,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        session()->flash('success', 'Voucher created successfully.');
        $this->dispatch('close-voucher-modal');
    }

    public function render(): mixed
    {
        $vouchers = Voucher::latest()->paginate(15);

        $accounts = [];
        if ($this->account_type === 'customer')
            $accounts = Customer::all();
        elseif ($this->account_type === 'supplier')
            $accounts = Supplier::all();
        elseif ($this->account_type === 'employee')
            $accounts = Employee::all();

        return view('components.admin.voucher-management', [
            'vouchers' => $vouchers,
            'accounts' => $accounts,
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Financial Vouchers') }}</h5>
            <div class="d-flex gap-2">
                <button wire:click="openCreateModal('receipt')" class="btn btn-success btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('New Receipt') }}
                </button>
                <button wire:click="openCreateModal('payment')" class="btn btn-danger btn-sm">
                    <i class="ri-subtract-line align-bottom me-1"></i> {{ __('New Payment') }}
                </button>
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
                            <th>ID</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Notes') }}</th>
                            <th class="text-end">{{ __('Created By') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vouchers as $voucher)
                            <tr>
                                <td>#{{ $voucher->id }}</td>
                                <td>{{ $voucher->date }}</td>
                                <td>
                                    <span class="badge {{ $voucher->type === 'receipt' ? 'bg-success' : 'bg-danger' }}">
                                        {{ __($voucher->type) }}
                                    </span>
                                </td>
                                <td>
                                    <small
                                        class="text-muted d-block text-uppercase">{{ __($voucher->account_type) }}</small>
                                    {{ $voucher->account->name ?? __('Deleted') }}
                                </td>
                                <td>
                                    <strong>{{ number_format($voucher->amount, 0) }} {{ $voucher->currency }}</strong>
                                    <br><small class="text-muted">{{ __('Rate') }}:
                                        {{ number_format($voucher->exchange_rate, 0) }}</small>
                                </td>
                                <td>{{ $voucher->notes }}</td>
                                <td class="text-end">{{ $voucher->creator->name ?? __('System') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.vouchers.print', $voucher->id) }}" target="_blank"
                                        class="btn btn-sm btn-soft-primary" title="{{ __('Print') }}">
                                        <i class="ri-printer-line"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $vouchers->links() }}
            </div>
        </div>
    </div>

    <!-- Voucher Modal -->
    <div wire:ignore.self class="modal fade" id="voucherModal" tabindex="-1" aria-labelledby="voucherModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="voucherModalLabel">{{ __('New') }} {{ __($type) }} {{ __('Voucher') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Date') }}</label>
                                <input type="date" wire:model="date" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Account Type') }}</label>
                                <select wire:model.live="account_type" class="form-select">
                                    <option value="customer">{{ __('Customer') }}</option>
                                    <option value="supplier">{{ __('Supplier') }}</option>
                                    <option value="employee">{{ __('Employee') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Select') }} {{ __($account_type) }}</label>
                            <select wire:model="account_id"
                                class="form-select @error('account_id') is-invalid @enderror">
                                <option value="">{{ __('Choose...') }}</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Amount') }}</label>
                                <input type="number" step="1" wire:model="amount"
                                    class="form-control @error('amount') is-invalid @enderror">
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Currency') }}</label>
                                <select wire:model.live="currency" class="form-select">
                                    <option value="USD">USD</option>
                                    <option value="IQD">IQD</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Exchange Rate (1 USD = ? IQD)') }}</label>
                            <input type="number" step="1" wire:model="exchange_rate" class="form-control">
                            <small class="text-muted">{{ __('Snapshot of current rate.') }}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea wire:model="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save Voucher') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-voucher-modal', () => {
                var myModalEl = document.getElementById('voucherModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });
            Livewire.on('close-voucher-modal', () => {
                var myModalEl = document.getElementById('voucherModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
</div>
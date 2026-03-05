<?php

use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';
    public $customerId;
    public $name, $phone, $address, $opening_balance = 0, $currency = 'IQD';
    public $isEditMode = false;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['name', 'phone', 'address', 'opening_balance', 'customerId', 'isEditMode']);
        $this->dispatch('open-customer-modal');
    }

    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        $this->customerId = $customer->id;
        $this->name = $customer->name;
        $this->phone = $customer->phone;
        $this->address = $customer->address;
        $this->opening_balance = $customer->opening_balance;
        $this->currency = $customer->currency;
        $this->isEditMode = true;
        $this->dispatch('open-customer-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'opening_balance' => 'required|numeric',
            'currency' => 'required|string|max:10',
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'opening_balance' => $this->opening_balance,
            'currency' => $this->currency,
            'updated_by' => Auth::id(),
        ];

        if ($this->isEditMode) {
            Customer::findOrFail($this->customerId)->update($data);
            session()->flash('success', __('Customer updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            $customer = Customer::create($data);

            // Create Opening Balance Entry in Ledger
            if ($this->opening_balance != 0) {
                \App\Models\CustomerLedger::create([
                    'customer_id' => $customer->id,
                    'date' => now(),
                    'type' => 'opening_balance',
                    'description' => __('Opening Balance'),
                    'debit' => $this->opening_balance > 0 ? $this->opening_balance : 0,
                    'credit' => $this->opening_balance < 0 ? abs($this->opening_balance) : 0,
                    'balance' => $this->opening_balance,
                    'currency' => $this->currency,
                    'created_by' => Auth::id(),
                ]);
            }

            session()->flash('success', __('Customer created successfully.'));
        }

        $this->dispatch('close-customer-modal');
    }

    public function delete($id)
    {
        Customer::findOrFail($id)->delete();
        session()->flash('success', __('Customer deleted successfully.'));
    }

    public function render(): mixed
    {
        $customers = Customer::where(function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('phone', 'like', '%' . $this->search . '%');
        })->latest()->paginate(10);

        return view('components.admin.customer-management', [
            'customers' => $customers
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Customer Management') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Customers...') }}">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Customer') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success mt-2">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Opening Balance') }}</th>
                            <th>{{ __('Currency') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            <div class="avatar-sm">
                                                <div class="avatar-title rounded-circle bg-info-subtle text-info">
                                                    {{ substr($customer->name, 0, 1) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fs-14 mb-0">{{ $customer->name }}</h6>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $customer->phone }}</td>
                                <td>{{ number_format($customer->opening_balance, 0) }}</td>
                                <td>{{ $customer->currency }}</td>
                                <td>{{ $customer->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.customers.ledger', $customer->id) }}"
                                        class="btn btn-sm btn-soft-primary" title="{{ __('View Ledger') }}">
                                        <i class="ri-file-list-3-line"></i>
                                    </a>
                                    <button wire:click="edit({{ $customer->id }})" class="btn btn-sm btn-soft-info" title="{{ __('Edit') }}"><i
                                            class="ri-edit-line"></i></button>
                                    <button wire:click="delete({{ $customer->id }})"
                                        onclick="return confirm('{{ __('Are you sure?') }}')" class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}"><i
                                            class="ri-delete-bin-line"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        </div>
    </div>

    <!-- Customer Modal -->
    <div wire:ignore.self class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalLabel">
                        {{ $isEditMode ? __('Edit Customer') : __('Add New Customer') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Customer Name') }}</label>
                            <input type="text" wire:model="name"
                                class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Phone Number') }}</label>
                            <input type="text" wire:model="phone"
                                class="form-control @error('phone') is-invalid @enderror"> @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Address') }}</label>
                            <textarea wire:model="address" class="form-control"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Opening Balance') }}</label>
                                <input type="number" step="1" wire:model="opening_balance"
                                    class="form-control @error('opening_balance') is-invalid @enderror">
                                @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Currency') }}</label>
                                <select wire:model="currency" class="form-select">
                                    <option value="IQD">IQD</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ $isEditMode ? __('Update') : __('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-customer-modal', () => {
                var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
                myModal.show();
            });
            Livewire.on('close-customer-modal', () => {
                var myModalEl = document.getElementById('customerModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
</div>
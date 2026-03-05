<?php

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $supplierId;
    public $name, $phone, $email, $address, $opening_balance = 0, $currency = 'IQD';
    public $isEditMode = false;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['name', 'phone', 'email', 'address', 'opening_balance', 'currency', 'supplierId', 'isEditMode']);
        $this->dispatch('open-supplier-modal');
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = $supplier->phone;
        $this->email = $supplier->email;
        $this->address = $supplier->address;
        $this->opening_balance = $supplier->opening_balance;
        $this->currency = $supplier->currency;
        $this->isEditMode = true;
        $this->dispatch('open-supplier-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'opening_balance' => 'required|numeric',
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'opening_balance' => $this->opening_balance,
            'currency' => $this->currency,
            'updated_by' => Auth::id(),
        ];

        if ($this->isEditMode) {
            Supplier::findOrFail($this->supplierId)->update($data);
            session()->flash('success', __('Supplier updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            $supplier = Supplier::create($data);

            // Create Opening Balance Entry in Ledger (if table exists/after migrate)
            try {
                if ($this->opening_balance != 0) {
                    SupplierLedger::create([
                        'supplier_id' => $supplier->id,
                        'date' => now(),
                        'type' => 'opening_balance',
                        'description' => __('Opening Balance'),
                        'debit' => $this->opening_balance < 0 ? abs($this->opening_balance) : 0,
                        'credit' => $this->opening_balance > 0 ? $this->opening_balance : 0,
                        'balance' => $this->opening_balance,
                        'currency' => $this->currency,
                        'created_by' => Auth::id(),
                    ]);
                }
            } catch (\Exception $e) {
                // Background error if table doesn't exist yet
            }

            session()->flash('success', __('Supplier created successfully.'));
        }

        $this->dispatch('close-supplier-modal');
    }

    public function delete($id)
    {
        $supplier = Supplier::findOrFail($id);
        if ($supplier->purchases()->count() > 0) {
            session()->flash('error', __('Cannot delete supplier with existing purchase records.'));
            return;
        }
        $supplier->delete();
        session()->flash('success', __('Supplier deleted successfully.'));
    }

    public function render(): mixed
    {
        $suppliers = Supplier::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('phone', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        return view('components.admin.supplier-management', [
            'suppliers' => $suppliers
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Supplier Management') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Suppliers...') }}">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Supplier') }}
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
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Address') }}</th>
                            <th>{{ __('Balance') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suppliers as $supplier)
                            <tr>
                                <td><strong>{{ $supplier->name }}</strong></td>
                                <td>
                                    {{ $supplier->phone }}<br>
                                    <small class="text-muted">{{ $supplier->email }}</small>
                                </td>
                                <td>{{ Str::limit($supplier->address, 30) }}</td>
                                <td>
                                    <span
                                        class="badge {{ $supplier->opening_balance >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ number_format($supplier->opening_balance, 0) }} {{ $supplier->currency }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.suppliers.ledger', $supplier->id) }}"
                                        class="btn btn-sm btn-soft-primary" title="{{ __('Financial Statement') }}">
                                        <i class="ri-file-list-3-line"></i>
                                    </a>
                                    <button wire:click="edit({{ $supplier->id }})" class="btn btn-sm btn-soft-info" title="{{ __('Edit') }}"><i
                                            class="ri-edit-line"></i></button>
                                    <button wire:click="delete({{ $supplier->id }})"
                                        onclick="return confirm('{{ __('Are you sure?') }}')" class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}"><i
                                            class="ri-delete-bin-line"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $suppliers->links() }}
            </div>
        </div>
    </div>

    <!-- Supplier Modal -->
    <div wire:ignore.self class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalLabel">
                        {{ $isEditMode ? __('Edit Supplier') : __('Add New Supplier') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Supplier Name') }}</label>
                                <input type="text" wire:model="name"
                                    class="form-control @error('name') is-invalid @enderror">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Phone Number') }}</label>
                                <input type="text" wire:model="phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Email Address') }}</label>
                                <input type="email" wire:model="email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Opening Balance') }}</label>
                                <div class="input-group">
                                    <input type="number" step="1" wire:model="opening_balance" class="form-control">
                                    <select wire:model="currency" class="form-select" style="max-width: 100px;">
                                        <option value="IQD">IQD</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </div>
                                <small class="text-muted">{{ __('Positive = We owe them (Credit), Negative = They owe us (Debit)') }}</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Address') }}</label>
                                <textarea wire:model="address" class="form-control" rows="2"></textarea>
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
            Livewire.on('open-supplier-modal', () => {
                var myModal = new bootstrap.Modal(document.getElementById('supplierModal'));
                myModal.show();
            });
            Livewire.on('close-supplier-modal', () => {
                var myModalEl = document.getElementById('supplierModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
</div>
<?php

use App\Models\Employee;
use App\Models\EmployeeLedger;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';
    public $showModal = false;
    public $employeeId;

    // Form Fields
    public $name;
    public $phone;
    public $email;
    public $position;
    public $salary = 0;
    public $commission_rate = 0;
    public $is_active = true;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->reset(['employeeId', 'name', 'phone', 'email', 'position', 'salary', 'commission_rate', 'is_active']);
        $this->showModal = true;
        $this->dispatch('open-employee-modal');
    }

    public function edit($id)
    {
        $employee = Employee::findOrFail($id);
        $this->employeeId = $employee->id;
        $this->name = $employee->name;
        $this->phone = $employee->phone;
        $this->email = $employee->email;
        $this->position = $employee->position;
        $this->salary = $employee->salary;
        $this->commission_rate = $employee->commission_rate;
        $this->is_active = $employee->is_active;

        $this->showModal = true;
        $this->dispatch('open-employee-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'position' => 'nullable|string|max:100',
            'salary' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'position' => $this->position,
            'salary' => $this->salary,
            'commission_rate' => $this->commission_rate,
            'is_active' => $this->is_active,
        ];

        if ($this->employeeId) {
            $employee = Employee::find($this->employeeId);
            $employee->update(array_merge($data, ['updated_by' => Auth::id()]));
            session()->flash('success', __('Employee updated successfully.'));
        } else {
            Employee::create(array_merge($data, ['created_by' => Auth::id()]));
            session()->flash('success', __('Employee created successfully.'));
        }

        $this->dispatch('close-employee-modal');
        $this->resetPage();
    }

    public function delete($id)
    {
        Employee::destroy($id);
        session()->flash('success', __('Employee deleted successfully.'));
    }

    public function render(): mixed
    {
        $employees = Employee::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('phone', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        return view('components.admin.employee-management', [
            'employees' => $employees,
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Employee Management') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search name or phone...') }}">
                <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('New Employee') }}
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
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Position') }}</th>
                            <th>{{ __('Salary') }}</th>
                            <th>{{ __('Comm %') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employees as $employee)
                            <tr>
                                <td>#{{ $employee->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="fs-14 mb-1">{{ $employee->name }}</h6>
                                            <p class="text-muted mb-0">{{ $employee->phone }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $employee->position ?? __('N/A') }}</td>
                                <td>{{ number_format($employee->salary, 0) }}</td>
                                <td>{{ $employee->commission_rate }}%</td>
                                <td>
                                    <span class="badge {{ $employee->is_active ? 'bg-success' : 'bg-danger' }}">
                                        {{ $employee->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="{{ route('admin.employees.ledger', $employee->id) }}"
                                            class="btn btn-sm btn-soft-info" title="{{ __('View Ledger') }}">
                                            <i class="ri-history-line"></i>
                                        </a>
                                        <button wire:click="edit({{ $employee->id }})" class="btn btn-sm btn-soft-primary"
                                            title="{{ __('Edit') }}">
                                            <i class="ri-edit-2-line"></i>
                                        </button>
                                        <button wire:confirm="{{ __('Are you sure?') }}"
                                            wire:click="delete({{ $employee->id }})" class="btn btn-sm btn-soft-danger"
                                            title="{{ __('Delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $employees->links() }}
            </div>
        </div>
    </div>

    <!-- Employee Modal -->
    <div wire:ignore.self class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalLabel">{{ $employeeId ? __('Edit') : __('New') }}
                        {{ __('Employee') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Full Name') }}</label>
                            <input type="text" wire:model="name"
                                class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" wire:model="phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" wire:model="email" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Position') }}</label>
                            <input type="text" wire:model="position" class="form-control"
                                placeholder="{{ __('e.g. Sales Manager') }}">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Monthly Salary') }}</label>
                                <input type="number" step="1" wire:model="salary" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Commission Rate (%)') }}</label>
                                <input type="number" step="0.01" wire:model="commission_rate" class="form-control">
                            </div>
                        </div>
                        <div class="form-check form-switch fs-14">
                            <input class="form-check-input" type="checkbox" wire:model="is_active" id="is_active">
                            <label class="form-check-label" for="is_active">{{ __('Active Status') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save Employee') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-employee-modal', () => {
                var myModalEl = document.getElementById('employeeModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });
            Livewire.on('close-employee-modal', () => {
                var myModalEl = document.getElementById('employeeModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
</div>
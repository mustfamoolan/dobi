<?php

use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';
    public $warehouseId;
    public $name, $location, $notes;
    public $is_active = true;
    public $isEditMode = false;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['name', 'location', 'notes', 'warehouseId', 'isEditMode', 'is_active']);
        $this->is_active = true;
        $this->dispatch('open-warehouse-modal');
    }

    public function edit($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $this->warehouseId = $warehouse->id;
        $this->name = $warehouse->name;
        $this->location = $warehouse->location;
        $this->notes = $warehouse->notes;
        $this->is_active = $warehouse->is_active;
        $this->isEditMode = true;
        $this->dispatch('open-warehouse-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $data = [
            'name' => $this->name,
            'location' => $this->location,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditMode) {
            Warehouse::findOrFail($this->warehouseId)->update($data);
            session()->flash('success', __('Warehouse updated successfully.'));
        } else {
            Warehouse::create($data + ['is_active' => true]);
            session()->flash('success', __('Warehouse created successfully.'));
        }

        $this->dispatch('close-warehouse-modal');
    }

    public function delete($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        // Prevent deleting if it has stock movements
        if ($warehouse->stockMovements()->count() > 0) {
            session()->flash('error', __('Cannot delete warehouse with existing stock movements.'));
            return;
        }

        $warehouse->delete();
        session()->flash('success', __('Warehouse deleted successfully.'));
    }

    public function render(): mixed
    {
        $warehouses = Warehouse::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('location', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        return view('components.admin.warehouse-management', [
            'warehouses' => $warehouses
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Warehouse Management') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Warehouses...') }}">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Warehouse') }}
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
                            <th>{{ __('Location') }}</th>
                            <th>{{ __('Notes') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouses as $warehouse)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            <div class="avatar-sm">
                                                <div class="avatar-title rounded-circle bg-primary-subtle text-primary">
                                                    <i class="ri-store-2-line"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fs-14 mb-0">{{ $warehouse->name }}</h6>
                                            @if(!$warehouse->is_active)
                                                <span class="badge bg-danger-subtle text-danger">{{ __('Inactive') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $warehouse->location }}</td>
                                <td>{{ $warehouse->notes }}</td>
                                <td>{{ $warehouse->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="{{ route('admin.warehouses.show', $warehouse->id) }}"
                                            class="btn btn-sm btn-soft-primary" title="{{ __('View') }}" wire:navigate>
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <button wire:click="edit({{ $warehouse->id }})" class="btn btn-sm btn-soft-info"
                                            title="{{ __('Edit') }}">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button wire:click="delete({{ $warehouse->id }})"
                                            onclick="return confirm('{{ __('Are you sure?') }}')"
                                            class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    {{ __('No records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $warehouses->links() }}
            </div>
        </div>
    </div>

    <!-- Warehouse Modal -->
    <div wire:ignore.self class="modal fade" id="warehouseModal" tabindex="-1" aria-labelledby="warehouseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="warehouseModalLabel">
                        {{ $isEditMode ? __('Edit Warehouse') : __('Add New Warehouse') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Warehouse Name') }}</label>
                            <input type="text" wire:model="name"
                                class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Location') }}</label>
                            <input type="text" wire:model="location"
                                class="form-control @error('location') is-invalid @enderror">
                            @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea wire:model="notes"
                                class="form-control @error('notes') is-invalid @enderror"></textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" wire:model="is_active"
                                id="is_active_switch">
                            <label class="form-check-label" for="is_active_switch">{{ __('Active Status') }}</label>
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
            Livewire.on('open-warehouse-modal', () => {
                new bootstrap.Modal(document.getElementById('warehouseModal')).show();
            });
            Livewire.on('close-warehouse-modal', () => {
                var modal = bootstrap.Modal.getInstance(document.getElementById('warehouseModal'));
                if (modal) modal.hide();
            });
        });
    </script>
</div>
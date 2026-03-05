<?php

use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $categoryId;
    public $name;
    public $isEditMode = false;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['name', 'categoryId', 'isEditMode']);
        $this->dispatch('open-category-modal');
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->isEditMode = true;
        $this->dispatch('open-category-modal');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $data = [
            'name' => $this->name,
            'updated_by' => Auth::id(),
        ];

        if ($this->isEditMode) {
            Category::findOrFail($this->categoryId)->update($data);
            session()->flash('success', __('Category updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            Category::create($data);
            session()->flash('success', __('Category created successfully.'));
        }

        $this->dispatch('close-category-modal');
    }

    public function delete($id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            session()->flash('error', __('Cannot delete category with associated products.'));
            return;
        }
        $category->delete();
        session()->flash('success', __('Category deleted successfully.'));
    }

    public function render(): mixed
    {
        $categories = Category::where('name', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        return view('components.admin.category-management', [
            'categories' => $categories
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Category Management') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Categories...') }}">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add Category') }}
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
                            <th>{{ __('Products Count') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                            <tr>
                                <td><strong>{{ $category->name }}</strong></td>
                                <td><span
                                        class="badge bg-info-subtle text-info">{{ $category->products_count ?? $category->products()->count() }}</span>
                                </td>
                                <td>{{ $category->creator->name ?? __('System') }}</td>
                                <td>{{ $category->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <button wire:click="edit({{ $category->id }})" class="btn btn-sm btn-soft-info" title="{{ __('Edit') }}"><i
                                            class="ri-edit-line"></i></button>
                                    <button wire:click="delete({{ $category->id }})"
                                        onclick="return confirm('{{ __('Are you sure?') }}')" class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}"><i
                                            class="ri-delete-bin-line"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $categories->links() }}
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div wire:ignore.self class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">
                        {{ $isEditMode ? __('Edit Category') : __('Add New Category') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Category Name') }}</label>
                            <input type="text" wire:model="name"
                                class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
            Livewire.on('open-category-modal', () => {
                var myModal = new bootstrap.Modal(document.getElementById('categoryModal'));
                myModal.show();
            });
            Livewire.on('close-category-modal', () => {
                var myModalEl = document.getElementById('categoryModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
</div>
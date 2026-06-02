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
            $category = Category::findOrFail($this->categoryId);
            $category->update($data);
            \App\Services\ActivityLogger::log('updated', __('Updated category: :name', ['name' => $category->name]), $category);
            session()->flash('success', __('Category updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            $category = Category::create($data);
            \App\Services\ActivityLogger::log('created', __('Created category: :name', ['name' => $category->name]), $category);
            session()->flash('success', __('Category created successfully.'));
        }

        $this->dispatch('close-category-modal');
    }

    public function delete($id)
    {
        $category = Category::findOrFail($id);
        $name = $category->name;
        if ($category->products()->count() > 0) {
            session()->flash('error', __('Cannot delete category with associated products.'));
            return;
        }
        $category->delete();
        \App\Services\ActivityLogger::log('deleted', __('Deleted category: :name', ['name' => $name]));
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

            <div class="row g-4">
                @forelse($categories as $category)
                    <div class="col-xl-3 col-lg-4 col-sm-6">
                        <div class="card h-100 shadow-sm hover-shadow border border-light-subtle rounded-4" 
                             style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.08)';"
                             onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                            <div class="card-body text-center p-4" 
                                 onclick="window.location.href='{{ route('admin.categories.show', $category->id) }}'">
                                <div class="avatar-md mx-auto mb-3 bg-primary-subtle text-primary rounded-4 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="ri-folder-open-fill fs-2" style="font-size: 2rem !important;"></i>
                                </div>
                                <h5 class="fs-16 mb-2 text-dark fw-bold">{{ $category->name }}</h5>
                                <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill">
                                    {{ $category->products_count ?? $category->products()->count() }} {{ __('Products') }}
                                </span>
                            </div>
                            <div class="card-footer bg-transparent border-top-0 d-flex justify-content-center gap-2 pb-4">
                                <button wire:click="edit({{ $category->id }})" class="btn btn-sm btn-soft-info px-3" title="{{ __('Edit') }}">
                                    <i class="ri-edit-line"></i> {{ __('Edit') }}
                                </button>
                                <button wire:click="delete({{ $category->id }})"
                                    onclick="event.stopPropagation(); return confirm('{{ __('Are you sure?') }}')" class="btn btn-sm btn-soft-danger px-3" title="{{ __('Delete') }}">
                                    <i class="ri-delete-bin-line"></i> {{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 py-5 text-center text-muted">
                        <i class="ri-folder-warning-line fs-2 mb-2"></i>
                        <p>{{ __('No categories found.') }}</p>
                    </div>
                @endforelse
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
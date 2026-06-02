<?php

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $userId;
    public $name, $email, $phone, $role = 'staff', $password;
    public $isEditMode = false;

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['name', 'email', 'phone', 'role', 'password', 'userId', 'isEditMode']);
        $this->dispatch('open-user-modal');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->role = $user->role;
        $this->isEditMode = true;
        $this->password = '';
        $this->dispatch('open-user-modal');
    }

    public function save()
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $this->userId,
            'phone' => 'nullable',
            'role' => 'required',
            'password' => $this->isEditMode ? 'nullable|min:6' : 'required|min:6',
        ];

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'updated_by' => Auth::id(),
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->isEditMode) {
            $user = User::findOrFail($this->userId);
            $user->update($data);
            \App\Services\ActivityLogger::log('updated', __('Updated system user: :name', ['name' => $user->name]), $user);
            session()->flash('success', __('User updated successfully.'));
        } else {
            $data['created_by'] = Auth::id();
            $user = User::create($data);
            \App\Services\ActivityLogger::log('created', __('Created system user: :name', ['name' => $user->name]), $user);
            session()->flash('success', __('User created successfully.'));
        }

        $this->dispatch('close-user-modal');
    }

    public function delete($id)
    {
        if ($id == Auth::id()) {
            session()->flash('error', __('You cannot delete yourself.'));
            return;
        }
        $user = User::findOrFail($id);
        $name = $user->name;
        $user->delete();
        \App\Services\ActivityLogger::log('deleted', __('Deleted system user: :name', ['name' => $name]));
        session()->flash('success', __('User deleted successfully.'));
    }

    public function render(): mixed
    {
        $users = User::where(function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->orWhere('phone', 'like', '%' . $this->search . '%');
        })->latest()->paginate(10);

        return view('components.admin.user-management', [
            'users' => $users
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('User Management') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search Users...') }}">
                <button wire:click="openModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('Add User') }}
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
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3" x-data="{
                                        isOnline() { return $store.presence && $store.presence.isOnline({{ $user->id }}); },
                                        getLastSeen() { return $store.presence ? $store.presence.getLastSeenText({{ $user->id }}, '{{ $user->last_seen ? $user->last_seen->toISOString() : '' }}') : '{{ $user->lastSeenText() }}'; }
                                    }">
                                        <!-- Pulsing Status Dot -->
                                        <div class="position-relative d-flex align-items-center justify-content-center">
                                            <span class="rounded-circle"
                                                  style="width: 10px; height: 10px; transition: all 0.3s ease;"
                                                  x-bind:class="isOnline() ? 'bg-success' : 'bg-secondary'">
                                            </span>
                                            <!-- Pulse animation (only active when online) -->
                                            <span class="position-absolute rounded-circle border border-success"
                                                  style="width: 100%; height: 100%; animation: pulse-ring 2s infinite;"
                                                  x-show="isOnline()">
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold fs-14 text-dark">{{ $user->name }}</span>
                                                @if($user->id === Auth::id())
                                                    <span class="badge bg-light text-secondary border border-secondary-subtle px-1 py-0" style="font-size: 10px;">You</span>
                                                @endif
                                            </div>
                                            <span class="d-block mt-1" style="font-size: 12px; font-weight: 500;"
                                                  x-bind:class="isOnline() ? 'text-success' : 'text-secondary'"
                                                  x-text="isOnline() ? '{{ __('Active now') }}' : getLastSeen()">
                                                  <!-- Fallback -->
                                                  <span class="{{ $user->isOnline() ? 'text-success' : 'text-secondary' }}">
                                                      {{ $user->isOnline() ? __('Active now') : $user->lastSeenText() }}
                                                  </span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone }}</td>
                                <td>
                                    <span
                                        class="badge {{ $user->role == 'admin' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                        {{ __($user->role) }}
                                    </span>
                                </td>
                                <td>{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <button wire:click="edit({{ $user->id }})" class="btn btn-sm btn-soft-info" title="{{ __('Edit') }}"><i
                                            class="ri-edit-line"></i></button>
                                    <button wire:click="delete({{ $user->id }})" onclick="return confirm('{{ __('Are you sure?') }}')"
                                        class="btn btn-sm btn-soft-danger" title="{{ __('Delete') }}"><i class="ri-delete-bin-line"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div wire:ignore.self class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">{{ $isEditMode ? __('Edit User') : __('Add New User') }}</h5>
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
                        <div class="mb-3">
                            <label class="form-label">{{ __('Email Address') }}</label>
                            <input type="email" wire:model="email"
                                class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Phone Number') }}</label>
                            <input type="text" wire:model="phone"
                                class="form-control @error('phone') is-invalid @enderror">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Role') }}</label>
                            <select wire:model="role" class="form-select @error('role') is-invalid @enderror">
                                <option value="staff">{{ __('Staff') }}</option>
                                <option value="admin">{{ __('Admin') }}</option>
                            </select>
                            @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Password') }}
                                {{ $isEditMode ? __('(Leave blank to keep current)') : '' }}</label>
                            <input type="password" wire:model="password"
                                class="form-control @error('password') is-invalid @enderror">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
            Livewire.on('open-user-modal', () => {
                var myModal = new bootstrap.Modal(document.getElementById('userModal'));
                myModal.show();
            });
            Livewire.on('close-user-modal', () => {
                var myModalEl = document.getElementById('userModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });
        });
    </script>
    
    <style>
    @keyframes pulse-ring {
        0% { transform: scale(0.8); opacity: 0.5; }
        100% { transform: scale(2.5); opacity: 0; }
    }
    </style>
</div>
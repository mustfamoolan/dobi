<?php

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $userId;
    public $event;
    public $search;
    public $fromDate;
    public $toDate;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingUserId() { $this->resetPage(); }
    public function updatingEvent() { $this->resetPage(); }
    public function updatingFromDate() { $this->resetPage(); }
    public function updatingToDate() { $this->resetPage(); }

    public function render()
    {
        $query = ActivityLog::with('user')->latest();

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->event) {
            $query->where('event', $this->event);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('description', 'like', '%' . $this->search . '%')
                  ->orWhere('subject_type', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->fromDate) {
            $query->whereDate('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate) {
            $query->whereDate('created_at', '<=', $this->toDate);
        }

        return view('components.admin.activity-log', [
            'logs' => $query->paginate(30),
            'users' => User::all(),
            'events' => ActivityLog::select('event')->distinct()->pluck('event')
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('System Activity Log') }}</h5>
            <div class="d-flex gap-2">
                <input type="text" wire:model.live.debounce.300ms="search" class="form-control form-control-sm" placeholder="{{ __('Search description...') }}">
                <select wire:model.live="userId" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">{{ __('All Users') }}</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="event" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">{{ __('All Events') }}</option>
                    @foreach($events as $e)
                        <option value="{{ $e }}">{{ __($e) }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Event') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('IP Address') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            <div class="avatar-xxs">
                                                <span class="avatar-title rounded-circle bg-primary-subtle text-primary">
                                                    {{ substr($log->user->name ?? '?', 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            {{ $log->user->name ?? 'System' }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($log->event) {
                                            'created', 'added' => 'success',
                                            'updated', 'modified' => 'info',
                                            'deleted', 'removed' => 'danger',
                                            'transferred' => 'primary',
                                            'invoice_created' => 'warning',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} text-uppercase">
                                        {{ __($log->event) }}
                                    </span>
                                </td>
                                <td>{{ $log->description }}</td>
                                <td class="text-muted small">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">{{ __('No activity records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>

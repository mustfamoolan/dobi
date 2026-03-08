<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use Livewire\WithPagination;

    protected $listeners = ['refreshNotifications' => '$refresh'];

    public function getNotificationsProperty()
    {
        return Auth::user()->unreadNotifications()->take(10)->get();
    }

    public function getUnreadCountProperty()
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
    }
};
?>

<div class="dropdown features-dropdown" wire:poll.15s>
    <button type="button" class="btn icon-btn btn-text-primary rounded-circle position-relative"
        id="page-header-notifications-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        aria-haspopup="true" aria-expanded="false">
        <i class="ri-notification-2-line fs-20"></i>
        @if($this->unreadCount > 0)
            <span class="position-absolute translate-middle badge rounded-pill p-1 min-w-20px badge text-bg-danger">
                {{ $this->unreadCount }}
            </span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
        aria-labelledby="page-header-notifications-dropdown">
        <div class="dropdown-header d-flex align-items-center py-3">
            <h6 class="mb-0 me-auto">{{ __('Notifications') }}</h6>
            <div class="d-flex align-items-center h6 mb-0">
                @if($this->unreadCount > 0)
                    <span class="badge bg-primary me-2">{{ $this->unreadCount }} {{ __('New') }}</span>
                @endif

                <div class="dropdown">
                    <a href="#!" class="btn btn-text-primary rounded-pill icon-btn-sm"
                        id="notification-settings-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ri-more-2-fill"></i>
                    </a>

                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notification-settings-dropdown">
                        <span class="dropdown-header fw-medium text-body">{{ __('Settings') }}</span>
                        <a class="dropdown-item" href="#!" wire:click.prevent="markAllAsRead">
                            <i class="ri-checkbox-circle-line me-1"></i> {{ __('Mark all as read') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <ul class="list-group list-group-flush header-notification-scrollable" data-simplebar
            style="max-height: 300px;">
            @forelse($this->notifications as $notification)
                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <div
                                    class="avatar-item avatar avatar-title {{ $notification->data['type'] === 'danger' ? 'bg-danger-subtle text-danger' :
                ($notification->data['type'] === 'warning' ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary') }}">
                                    <i class="{{ $notification->data['icon'] ?? 'ri-notification-line' }}"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 small">{{ __($notification->data['title']) }}</h6>
                                <small class="mb-1 d-block text-body">{{ $notification->data['message'] }}</small>
                                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                            </div>
                            <div class="flex-shrink-0 ms-2">
                                <button wire:click="markAsRead('{{ $notification->id }}')"
                                    class="btn btn-sm btn-ghost-secondary px-1" title="{{ __('Mark as Read') }}">
                                    <i class="ri-check-line"></i>
                                </button>
                            </div>
                        </div>
                    </li>
            @empty
                <li class="list-group-item text-center py-4">
                    <div class="avatar-md mx-auto mb-3">
                        <div class="avatar-title bg-light text-muted rounded-circle fs-24">
                            <i class="ri-notification-off-line"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0">{{ __('No new notifications') }}</p>
                </li>
            @endforelse
        </ul>

        @if(count($this->notifications) > 0)
            <div class="card-footer text-center border-top">
                <a href="#!" class="btn btn-sm btn-link text-primary">{{ __('View All Notifications') }}</a>
            </div>
        @endif
    </div>
</div>
<div class="hstack flex-wrap gap-3 mb-4">
    <div class="flex-grow-1">
        <h4 class="mb-1 fw-semibold">@yield('sub-title')</h4>
        <nav>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard', ['page' => 'index']) }}">@yield('pagetitle')</a>
                </li>
                <li class="breadcrumb-item active d-flex align-items-center gap-2" aria-current="page">
                    <a href="javascript:void(0)" onclick="window.history.back()" class="text-secondary" title="{{ __('Back') }}">
                        <i class="ri-arrow-right-fill fs-18"></i>
                    </a>
                    @yield('sub-title')
                </li>
            </ol>
        </nav>
    </div>
    <div class="d-flex my-xl-auto align-items-center flex-shrink-0 gap-2">
        @if (!empty(trim(View::yieldContent('modalTarget'))))
            <a href="javascript:void(0)" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                data-bs-target="#@yield('modalTarget')">
                <i class="ri-add-line align-bottom me-1"></i> @yield('buttonTitle')
            </a>
        @endif

        @if (!empty(trim(View::yieldContent('link'))))
            <a href="{{ url(trim(View::yieldContent('link'))) }}" class="btn btn-sm btn-info">
                @yield('buttonTitle')
            </a>
        @endif
    </div>
</div>
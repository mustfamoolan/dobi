<!-- START HEADER -->
<header class="app-header">
  <div class="container-fluid">
    <div class="nav-header">

      <div class="header-left hstack gap-3">
        <!-- HORIZONTAL BRAND LOGO -->
        <div class="app-sidebar-logo app-horizontal-logo justify-content-center align-items-center">
          <a href="{{ url('/') }}">
            @if($settings && $settings->logo)
              <img height="35" class="app-sidebar-logo-default" alt="DOKKAN" loading="lazy"
                src="{{ asset('storage/' . $settings->logo) }}">
            @else
              <img height="35" class="app-sidebar-logo-default" alt="DOKKAN" loading="lazy"
                src="{{ asset('assets/images/DOKKAN.png') }}">
            @endif
          </a>
        </div>

        <!-- Sidebar Toggle Btn -->
        <button type="button" class="btn btn-light-light icon-btn sidebar-toggle d-none d-md-block"
          aria-expanded="false" aria-controls="main-menu">
          <span class="visually-hidden">Toggle sidebar</span>
          <i class="ri-menu-2-fill"></i>
        </button>

        <!-- Sidebar Toggle for Mobile -->
        <button class="btn btn-light-light icon-btn d-md-none small-screen-toggle" id="smallScreenSidebarLabel"
          type="button" data-bs-toggle="offcanvas" data-bs-target="#smallScreenSidebar"
          aria-controls="smallScreenSidebar">
          <span class="visually-hidden">Sidebar toggle for mobile</span>
          <i class="ri-arrow-right-fill"></i>
        </button>

        <!-- Sidebar Toggle for Horizontal Menu -->
        <button class="btn btn-light-light icon-btn d-lg-none small-screen-horizontal-toggle" type="button"
          aria-expanded="false" aria-controls="main-menu">
          <span class="visually-hidden">Sidebar toggle for horizontal</span>
          <i class="ri-arrow-right-fill"></i>
        </button>

        <!-- Smart Search Component -->
        <div class="header-search-container flex-grow-1" style="max-width: 400px;">
          <livewire:admin.global-search />
        </div>
      </div>

      <div class="header-right hstack gap-3">
        <div class="hstack gap-0 gap-sm-1">




          <!-- Language -->
          <div class="dropdown features-dropdown" id="language-dropdown">
            <a href="#!" class="btn icon-btn btn-text-primary rounded-circle" data-bs-toggle="dropdown"
              aria-expanded="false">
              <div class="avatar-item avatar-xs">
                @if(app()->getLocale() == 'ar')
                  <img class="img-fluid avatar-xs" src="{{ asset('assets/images/flags/ae.svg') }}" loading="lazy"
                    alt="ar">
                @else
                  <img class="img-fluid avatar-xs" src="{{ asset('assets/images/flags/us.svg') }}" loading="lazy"
                    alt="en">
                @endif
              </div>
            </a>

            <div class="dropdown-menu dropdown-menu-end">
              <a href="{{ route('lang.switch', 'ar') }}"
                class="dropdown-item py-2 {{ app()->getLocale() == 'ar' ? 'active' : '' }}">
                <img src="{{ asset('assets/images/flags/ae.svg') }}" alt="ar" loading="lazy"
                  class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                <span class="align-middle">{{ __('Arabic') }}</span>
              </a>
              <a href="{{ route('lang.switch', 'en') }}"
                class="dropdown-item py-2 {{ app()->getLocale() == 'en' ? 'active' : '' }}">
                <img src="{{ asset('assets/images/flags/us.svg') }}" alt="en" loading="lazy"
                  class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                <span class="align-middle">{{ __('English') }}</span>
              </a>
            </div>
          </div>

          <!-- Theme -->
          <div class="dropdown features-dropdown d-none d-sm-block">
            <button type="button" class="btn icon-btn btn-text-primary rounded-circle" data-bs-toggle="dropdown"
              aria-expanded="false">
              <span class="visually-hidden">Light or Dark Mode Switch</span>
              <i class="ri-sun-line fs-20"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end header-language-scrollable" data-simplebar>

              <div class="dropdown-item cursor-pointer" id="light-theme">
                <span class="hstack gap-2 align-middle"><i class="ri-sun-line"></i>Light</span>
              </div>
              <div class="dropdown-item cursor-pointer" id="dark-theme">
                <span class="hstack gap-2 align-middle"><i class="ri-moon-clear-line"></i>Dark</span>
              </div>
              <div class="dropdown-item cursor-pointer" id="system-theme">
                <span class="hstack gap-2 align-middle"><i class="ri-computer-line"></i>System</span>
              </div>

            </div>
          </div>

          <!-- Notification -->
          <livewire:admin.notification-dropdown />

          <!-- Fullscreen -->
          <button type="button" id="fullscreen-button"
            class="btn icon-btn btn-text-primary rounded-circle custom-toggle d-none d-sm-block" aria-pressed="false">
            <span class="visually-hidden">Toggle Fullscreen</span>
            <span class="icon-on">
              <i class="ri-fullscreen-exit-line fs-16"></i>
            </span>
            <span class="icon-off">
              <i class="ri-fullscreen-line fs-16"></i>
            </span>
          </button>
        </div>

        <!-- Profile Section -->
        @auth
          <div class="dropdown profile-dropdown features-dropdown">
            <button type="button" id="accountNavbarDropdown"
              class="btn profile-btn shadow-none px-0 hstack gap-0 gap-sm-3" data-bs-toggle="dropdown"
              aria-expanded="false" data-bs-auto-close="outside" data-bs-dropdown-animation>
              <span class="position-relative">
                <span class="avatar-item avatar overflow-hidden">
                  <img class="img-fluid" src="{{ asset('assets/images/avatar/avatar-1.jpg') }}" alt="avatar image">
                </span>
                <span
                  class="position-absolute border-2 border border-white h-12px w-12px rounded-circle bg-success end-0 bottom-0"></span>
              </span>
              <span>
                <span class="h6 d-none d-xl-inline-block text-start fw-semibold mb-0">{{ Auth::user()->name }}</span>
                <span class="d-none d-xl-block fs-12 text-start text-muted">Administrator</span>
              </span>
            </button>

            <div class="dropdown-menu dropdown-menu-end header-language-scrollable"
              aria-labelledby="accountNavbarDropdown">
              <a class="dropdown-item" href="javascript:void(0);"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out</a>
              <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
              </form>
            </div>
          </div>
        @else
          <div class="hstack gap-2 d-none d-sm-flex">
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Sign In</a>
          </div>
        @endauth
      </div>
    </div>
  </div>

</header>
<!-- END HEADER -->
{{-- Layout JS --}}
@if(!empty($horizontal))
    <script src="{{ asset('assets/js/layout/' . $horizontal . '.js') }}"></script>
@elseif(!empty($twocolumn))
    <script src="{{ asset('assets/js/layout/' . $twocolumn . '.js') }}"></script>
@elseif(!empty($compact))
    <script src="{{ asset('assets/js/layout/' . $compact . '.js') }}"></script>
@elseif(!empty($semibox))
    <script src="{{ asset('assets/js/layout/' . $semibox . '.js') }}"></script>
@elseif(!empty($smallicon))
    <script src="{{ asset('assets/js/layout/' . $smallicon . '.js') }}"></script>
@elseif(!empty($auth))
    <script src="{{ asset('assets/js/layout/' . $auth . '.js') }}"></script>
@else
    <script src="{{ asset('assets/js/layout/layout-default.js') }}"></script>
@endif

<script src="{{ asset('assets/js/layout/layout.js') }}"></script>

{{-- CSS Dependencies --}}
<link rel="stylesheet" href="{{ asset('assets/libs/choices.js/public/assets/styles/choices.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/libs/simplebar/simplebar.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/libs/sweetalert2/sweetalert2.min.css') }}">
@if(app()->getLocale() == 'ar')
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-rtl.min.css') }}" id="bootstrap-style">
    <link rel="stylesheet" href="{{ asset('assets/css/app-rtl.min.css') }}" id="app-style">
    <link rel="stylesheet" href="{{ asset('assets/css/custom-rtl.min.css') }}" id="custom-style">
@else
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}" id="bootstrap-style">
    <link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}" id="app-style">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.min.css') }}" id="custom-style">
@endif

<style>
    /* DOKKAN Minimalist Cleanup - Updated for Functional Navigation */
    
    /* Clean up the breadcrumb item container */
    .breadcrumb-item.active.d-flex {
        display: inline-block !important;
        gap: 0 !important;
    }

    /* Standardize breadcrumb separators to a clear arrow */
    .breadcrumb-item + .breadcrumb-item::before {
        content: "\f105" !important; /* Remix Icon ri-arrow-right-s-line */
        font-family: 'remixicon' !important;
        color: #adb5bd !important;
        font-size: 14px !important;
        vertical-align: middle;
        padding-left: 8px;
        padding-right: 8px;
    }

    /* Modern Back Button Aesthetic */
    .back-btn-modern {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0.6;
        text-decoration: none !important;
    }

    .back-btn-modern:hover {
        opacity: 1;
        transform: translateX(3px); /* Subtle move in RTL (points right) */
        color: var(--vz-primary) !important;
    }

    .back-btn-modern i {
        line-height: 1;
    }

    @keyframes pulse-ring {
        0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(10, 179, 156, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(10, 179, 156, 0); }
        100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(10, 179, 156, 0); }
    }

    .pulse-animation {
        animation: pulse-ring 2s infinite;
    }
</style>

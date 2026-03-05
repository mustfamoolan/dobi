<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}"
    class="h-100">

<head>
    <meta charset="utf-8">
    @include('partials.title-meta')

    @include('partials.datatable-css')
    @yield('css')
    <script>
        (function () {
            const lang = "{{ app()->getLocale() }}";
            const dir = lang === 'ar' ? 'rtl' : 'ltr';
            if (sessionStorage.getItem('dir') !== dir) {
                sessionStorage.setItem('dir', dir);
            }
        })();
    </script>
    @include('partials.head-css')
</head>

<body>

    @include('partials.header')
    @include('partials.sidebar')
    @include('partials.preloader')


    <main class="app-wrapper">
        <div class="app-container">

            @include('partials.breadcrumb')

            <!-- end page title -->

            @yield('content')
            @include('partials.bottom-wrapper')
            @include('partials.datatable-script')
            @yield('js')

</body>

</html>
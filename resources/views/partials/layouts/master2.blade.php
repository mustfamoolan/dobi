<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="utf-8">
    @include('partials.title-meta')

    @yield('css')
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

            @include('partials.social-share-modal')
            @include('partials.bottom-wrapper')

            @yield('js')

</body>

</html>
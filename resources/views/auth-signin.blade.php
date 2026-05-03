@extends('partials.layouts.master-auth')

@section('title', 'تسجيل الدخول | دكان - نظام إدارة الموارد المتكامل')

@section('css')
    @include('partials.head-css', ['auth' => 'layout-auth'])
@endsection

@section('content')

    <!-- START -->
    <div class="account-pages">
        <img src="{{ asset('assets/images/auth/auth_bg.jpeg') }}" alt="auth_bg" class="auth-bg light">
        <img src="{{ asset('assets/images/auth/auth_bg_dark.jpg') }}" alt="auth_bg_dark" class="auth-bg dark">
        <div class="container">
            <div class="justify-content-center row gy-0">

                <div class="col-lg-6 auth-banners">
                    <div class="bg-login card card-body m-0 h-100 border-0">
                        <img src="{{ asset('assets/images/auth/bg-img-2.png') }}" class="img-fluid auth-banner"
                            alt="auth-banner">

                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="auth-box card card-body m-0 h-100 border-0 justify-content-center">
                        <div class="mb-5 text-center">
                            <h4 class="fw-normal">مرحباً بك في <span
                                    class="fw-bold text-primary">{{ $settings->company_name ?? 'نظام دكان' }}</span></h4>
                            <p class="text-muted mb-0">يرجى إدخال بياناتك للوصول إلى حسابك.</p>
                        </div>
                        <form class="form-custom mt-10" action="{{ route('login') }}" method="POST">
                            @csrf
                            <div class="mb-5">
                                <label class="form-label" for="login-email">البريد الإلكتروني<span
                                        class="text-danger ms-1">*</span>
                                </label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="login-email" name="email" value="{{ old('email') }}"
                                    placeholder="أدخل البريد الإلكتروني" required autofocus>
                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="mb-5">
                                <label class="form-label" for="LoginPassword">كلمة المرور<span
                                        class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="LoginPassword"
                                        class="form-control @error('password') is-invalid @enderror" name="password"
                                        placeholder="أدخل كلمة المرور" data-visible="false" required>
                                    <a class="input-group-text bg-transparent toggle-password" href="javascript:;"
                                        data-target="password">
                                        <i class="ri-eye-off-line text-muted toggle-icon"></i>
                                    </a>
                                </div>
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="row mb-5">
                                <div class="col-sm-6 text-start">
                                    <div class="form-check form-check-sm d-flex align-items-center gap-2 mb-0">
                                        <input class="form-check-input" type="checkbox" name="remember" value="remember-me"
                                            id="remember-me" {{ old('remember') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="remember-me">
                                            تذكرني
                                        </label>
                                    </div>
                                </div>
                                <a href="auth-reset-password" class="col-sm-6 text-end">
                                    <span class="fs-14 text-muted">
                                        نسيت كلمة المرور؟
                                    </span>
                                </a>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-2 w-100 btn-loader">
                                <span class="indicator-label">
                                    تسجيل الدخول
                                </span>
                                <span class="indicator-progress flex gap-2 justify-content-center w-100">
                                    <span>جاري التحميل...</span>
                                    <i class="ri-loader-2-fill"></i>
                                </span>
                            </button>

                            {{-- Social Media Login Removed as per user request --}}

                            <p class="mb-0 mt-5 text-muted text-center">
                                ليس لديك حساب ؟
                                <a href="auth-signup" class="text-primary fw-medium text-decoraton-underline ms-1">
                                    إنشاء حساب جديد
                                </a>
                            </p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('js')

    <!-- App js -->
    <script type="module" src="{{ asset('assets/js/app.js') }}"></script>
@endsection
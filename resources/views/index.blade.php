@extends('partials.layouts.master')

@section('title', __('Dashboard'))
@section('sub-title', __('Overview'))
@section('pagetitle', __('Dashboard'))

@section('content')
    <div class="dashboard-minimal">
        {{-- Date Filter Row --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form action="{{ route('admin.dashboard', 'index') }}" method="GET" class="row g-3 align-items-end">
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label fw-bold text-muted small uppercase">فلترة سريعة</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="{{ route('admin.dashboard', ['page' => 'index', 'filter' => 'today']) }}" class="btn btn-outline-primary btn-sm {{ $filterType == 'today' ? 'active' : '' }}">اليوم</a>
                                    <a href="{{ route('admin.dashboard', ['page' => 'index', 'filter' => 'week']) }}" class="btn btn-outline-primary btn-sm {{ $filterType == 'week' ? 'active' : '' }}">الأسبوع</a>
                                    <a href="{{ route('admin.dashboard', ['page' => 'index', 'filter' => 'month']) }}" class="btn btn-outline-primary btn-sm {{ $filterType == 'month' ? 'active' : '' }}">الشهر</a>
                                    <a href="{{ route('admin.dashboard', ['page' => 'index', 'filter' => 'year']) }}" class="btn btn-outline-primary btn-sm {{ $filterType == 'year' ? 'active' : '' }}">السنة</a>
                                    <a href="{{ route('admin.dashboard', ['page' => 'index', 'filter' => 'all']) }}" class="btn btn-outline-primary btn-sm {{ $filterType == 'all' ? 'active' : '' }}">الكل</a>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label fw-bold text-muted small uppercase">من تاريخ</label>
                                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $fromDate }}">
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label fw-bold text-muted small uppercase">إلى تاريخ</label>
                                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $toDate }}">
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="ri-filter-3-line me-1"></i> تطبيق الفلتر
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        {{-- Section 1: Key Metrics --}}
        <div class="row g-3">
            {{-- Sales & Purchases --}}
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="card-title mb-0 text-muted uppercase fw-bold fs-12">{{ __('Financial Performance') }}</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row align-items-center">
                            <div class="col-6 border-end">
                                <div class="p-2">
                                    <p class="text-muted mb-1 fs-13">{{ __('Total Sales') }}</p>
                                    <h4 class="mb-1 fw-bold text-success">{{ number_format($total_sales_iqd, 0) }} <small class="fs-11">IQD</small></h4>
                                    <p class="mb-0 text-success-emphasis fw-semibold">{{ number_format($total_sales_usd, 2) }} <small class="fs-11">$</small></p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2">
                                    <p class="text-muted mb-1 fs-13">{{ __('Total Purchases') }}</p>
                                    <h4 class="mb-1 fw-bold text-danger">{{ number_format($total_purchases_iqd, 0) }} <small class="fs-11">IQD</small></h4>
                                    <p class="mb-0 text-danger-emphasis fw-semibold">{{ number_format($total_purchases_usd, 2) }} <small class="fs-11">$</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Treasury Balance --}}
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="card-title mb-0 text-muted uppercase fw-bold fs-12">إجمالي أرصدة الصناديق</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row align-items-center">
                            <div class="col-6 border-end">
                                <div class="p-2">
                                    <p class="text-muted mb-1 fs-13">العراقي</p>
                                    <h3 class="mb-0 fw-bold text-primary">{{ number_format($treasury_total, 0) }}</h3>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2">
                                    <p class="text-muted mb-1 fs-13">الدولار</p>
                                    <h3 class="mb-0 fw-bold text-dark">${{ number_format($treasury_total_usd, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Secondary Stats --}}
        <div class="row g-3 mt-1">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3">
                                <i class="ri-user-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-0">{{ __('Customers') }}</p>
                            <h5 class="mb-0 fw-bold">{{ number_format($customers_count) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3">
                                <i class="ri-stack-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-0">{{ __('Products') }}</p>
                            <h5 class="mb-0 fw-bold">{{ number_format($products_count) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                <i class="ri-shopping-bag-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-0">مبيعات اليوم</p>
                            <h5 class="mb-0 fw-bold">{{ number_format($sales_today, 0) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3">
                                <i class="ri-alert-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-0">النواقص</p>
                            <h5 class="mb-0 fw-bold text-danger">{{ $low_stock_products }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: Tables --}}
        <div class="row g-4 mt-2">
            {{-- Receivables & Payables Summary --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">ملخص المديونية</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 fs-13">ديون العملاء (بالعراقي)</p>
                                        <h5 class="mb-0 text-primary fw-bold">{{ number_format($total_receivables_iqd, 0) }} <small class="fs-11">IQD</small></h5>
                                    </div>
                                    <i class="ri-money-dollar-circle-line fs-2 text-primary opacity-25"></i>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 fs-13">ديون العملاء (بالدولار)</p>
                                        <h5 class="mb-0 text-success fw-bold">${{ number_format($total_receivables_usd, 2) }}</h5>
                                    </div>
                                    <i class="ri-coins-line fs-2 text-success opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Activities --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="card-title mb-0">{{ __('Recent Sales') }}</h5>
                        <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-link p-0 text-primary">{{ __('View All') }}</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('Customer') }}</th>
                                        <th class="text-end">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent_sales as $sale)
                                        <tr>
                                            <td><span class="text-muted">#{{ $sale->id }}</span></td>
                                            <td class="fw-medium">{{ $sale->customer->name ?? 'N/A' }}</td>
                                            <td class="text-end fw-bold">{{ number_format($sale->grand_total, 0) }} <small class="text-muted">{{ $sale->currency }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="card-title mb-0">آخر السندات</h5>
                        <a href="{{ route('admin.vouchers.index') }}" class="btn btn-sm btn-link p-0 text-primary">{{ __('View All') }}</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Type') }}</th>
                                        <th class="text-end">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent_vouchers as $voucher)
                                        <tr>
                                            <td>
                                                <span class="text-{{ $voucher->type == 'receipt' ? 'success' : 'danger' }} fw-bold">
                                                    {{ $voucher->type == 'receipt' ? 'قبض' : 'صرف' }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold">
                                                {{ number_format($voucher->amount, 0) }} <small class="text-muted">{{ $voucher->currency }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script type="module" src="{{ asset('assets/js/app.js') }}"></script>
@endsection
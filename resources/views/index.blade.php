@extends('partials.layouts.master')

@section('title', __('Dashboard') . ' | ' . __('Management Overview'))
@section('sub-title', __('Management Overview'))
@section('pagetitle', __('Dashboard'))

@section('content')
    <div class="e-commerce-dashboard">
        <div class="row">
            {{-- Total Sales Card --}}
            <div class="col-lg-3">
                <div class="card card-h-100 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="hstack flex-wrap justify-content-between gap-3 align-items-end">
                            <div class="flex-grow-1">
                                <div class="hstack gap-3 mb-3">
                                    <div class="bg-success-subtle text-success avatar avatar-item rounded-2">
                                        <i class="ri-shopping-cart-2-line fs-16 fw-medium"></i>
                                    </div>
                                    <h6 class="mb-0 fs-13">{{ __('Total Sales') }}</h6>
                                </div>
                                <h4 class="fw-semibold fs-5 mb-0">
                                    <span>{{ number_format($total_sales, 0) }}</span>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Total Purchases Card --}}
            <div class="col-lg-3">
                <div class="card card-h-100 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="hstack flex-wrap justify-content-between gap-3 align-items-end">
                            <div class="flex-grow-1">
                                <div class="hstack gap-3 mb-3">
                                    <div class="bg-danger-subtle text-danger avatar avatar-item rounded-2">
                                        <i class="ri-bill-line fs-16 fw-medium"></i>
                                    </div>
                                    <h6 class="mb-0 fs-13">{{ __('Total Purchases') }}</h6>
                                </div>
                                <h4 class="fw-semibold fs-5 mb-0">
                                    <span>{{ number_format($total_purchases, 0) }}</span>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Total Customers Card --}}
            <div class="col-lg-3">
                <div class="card card-h-100 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="hstack flex-wrap justify-content-between gap-3 align-items-end">
                            <div class="flex-grow-1">
                                <div class="hstack gap-3 mb-3">
                                    <div class="bg-primary-subtle text-primary avatar avatar-item rounded-2">
                                        <i class="ri-user-heart-line fs-16 fw-medium"></i>
                                    </div>
                                    <h6 class="mb-0 fs-13">{{ __('Total Customers') }}</h6>
                                </div>
                                <h4 class="fw-semibold fs-5 mb-0">
                                    <span>{{ $customers_count }}</span>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Total Products Card --}}
            <div class="col-lg-3">
                <div class="card card-h-100 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="hstack flex-wrap justify-content-between gap-3 align-items-end">
                            <div class="flex-grow-1">
                                <div class="hstack gap-3 mb-3">
                                    <div class="bg-info-subtle text-info avatar avatar-item rounded-2">
                                        <i class="ri-shopping-bag-3-line fs-16 fw-medium"></i>
                                    </div>
                                    <h6 class="mb-0 fs-13">{{ __('Registered Products') }}</h6>
                                </div>
                                <h4 class="fw-semibold fs-5 mb-0">
                                    <span>{{ $products_count }}</span>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ __('Recent Sales Invoices') }}</h5>
                        <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Sale ID') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Customer') }}</th>
                                        <th>{{ __('Currency') }}</th>
                                        <th>{{ __('Total Amount') }}</th>
                                        <th>{{ __('Exchange Rate Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recent_sales as $sale)
                                        <tr>
                                            <td><span class="fw-medium">#{{ $sale->id }}</span></td>
                                            <td>{{ $sale->date }}</td>
                                            <td>{{ $sale->customer->name ?? 'N/A' }}</td>
                                            <td>{{ $sale->currency }}</td>
                                            <td>{{ number_format($sale->grand_total, 0) }}</td>
                                            <td>{{ number_format($sale->exchange_rate, 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">{{ __('No sales found yet.') }}</td>
                                        </tr>
                                    @endforelse
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
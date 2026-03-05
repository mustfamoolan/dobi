@extends('partials.layouts.master')

@section('title', 'System Settings')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">General Settings</h4>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success mt-2">
                                {{ session('success') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name (Arabic)</label>
                                    <input type="text" name="company_name" class="form-control"
                                        value="{{ old('company_name', $setting->company_name) }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name (English)</label>
                                    <input type="text" name="company_name_en" class="form-control"
                                        value="{{ old('company_name_en', $setting->company_name_en) }}">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description / Business Type</label>
                                    <input type="text" name="description" class="form-control"
                                        value="{{ old('description', $setting->description) }}">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control"
                                        value="{{ old('address', $setting->address) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="{{ old('phone', $setting->phone) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        value="{{ old('email', $setting->email) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Default Currency</label>
                                    <input type="text" name="default_currency" class="form-control"
                                        value="{{ old('default_currency', $setting->default_currency) }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Exchange Rate (vs USD)</label>
                                    <input type="number" step="0.0001" name="exchange_rate" class="form-control"
                                        value="{{ old('exchange_rate', $setting->exchange_rate) }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Logo</label>
                                    <input type="file" name="logo" class="form-control">
                                    @if($setting->logo)
                                        <div class="mt-2">
                                            <img src="{{ asset('storage/' . $setting->logo) }}" alt="Logo" height="50">
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
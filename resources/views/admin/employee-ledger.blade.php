@extends('partials.layouts.master')
@section('title', 'Employee Ledger')
@section('content')
    <div class="page-content">
        <livewire:admin.employee-ledger :employeeId="$id" />
    </div>
@endsection
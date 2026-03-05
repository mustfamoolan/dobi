@extends('partials.layouts.master')

@section('title', 'Customer Ledger')

@section('content')
    <livewire:admin.customer-ledger :customerId="$id" />
@endsection
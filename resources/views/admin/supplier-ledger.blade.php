@extends('partials.layouts.master')
@section('title', 'Supplier Ledger')
@section('content')
    <div class="page-content">
        <livewire:admin.supplier-ledger :supplierId="$id" />
    </div>
@endsection
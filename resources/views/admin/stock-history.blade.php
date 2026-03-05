@extends('partials.layouts.master')

@section('title', 'Product Stock History')

@section('content')
    <livewire:admin.product-stock-history :productId="$id" />
@endsection
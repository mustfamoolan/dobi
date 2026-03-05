@extends('partials.layouts.master')
@section('title', __('Warehouse Details'))
@section('content')
    <div class="page-content">
        <livewire:admin.warehouse-show :id="$id" />
    </div>
@endsection
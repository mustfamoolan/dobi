@extends('partials.layouts.master')

@section('title', __('Category Details'))

@section('content')
    <livewire:admin.category-detail :id="$id" />
@endsection

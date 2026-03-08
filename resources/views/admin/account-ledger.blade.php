@extends('partials.layouts.master')

@section('title', __('Account Ledger'))

@section('content')
    <livewire:admin.account-ledger :accountId="$id" />
@endsection
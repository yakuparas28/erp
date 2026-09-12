@extends('app.layouts.app')

@section('title', __('General Manager Approvals'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('General Manager Approvals') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Second-level approvals waiting for you.') }}</p>
</div>

@include('hr::leaves._approval-table', ['requests' => $requests])
@endsection

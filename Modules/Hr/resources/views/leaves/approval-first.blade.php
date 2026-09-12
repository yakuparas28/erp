@extends('app.layouts.app')

@section('title', __('Unit Manager Approvals'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Unit Manager Approvals') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Leave requests waiting for the first-level approval you are authorized to decide.') }}</p>
</div>

@include('hr::leaves._approval-table', ['requests' => $requests])
@endsection

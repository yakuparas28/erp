@extends('app.layouts.app')

@section('title', __('Usage Rules'))

@section('content')
<h1 class="text-xl font-bold mb-4">{{ __('Usage Rules') }}</h1>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('app.fleet.usage-rules.update') }}" class="bg-white border border-border-color rounded-md p-4">
    @csrf @method('PUT')
    <label class="text-xs text-default block mb-1">İçerik (HTML)</label>
    <textarea name="icerik_html" rows="16" maxlength="65535" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono">{{ old('icerik_html', $rule->icerik_html) }}</textarea>
    <div class="text-xs text-default mt-2">Bu içerik personelin rezervasyon oluşturma ekranında pop-up olarak gösterilir.</div>
    <div class="flex justify-end mt-4">
        <button class="btn-sm bg-primary text-white">{{ __('Save') }}</button>
    </div>
</form>
@endsection

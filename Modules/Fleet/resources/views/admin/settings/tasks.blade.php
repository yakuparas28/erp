@extends('app.layouts.app')

@section('title', __('Scheduled Tasks'))

@section('content')
<h1 class="text-xl font-bold mb-4">{{ __('Scheduled Tasks') }}</h1>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <form method="POST" action="{{ route('app.fleet.settings.tasks.update') }}" class="bg-white border border-border-color rounded-md p-4">
        @csrf @method('PUT')
        <div class="font-semibold mb-3">Kritik Pencere (Bakım/Muayene)</div>
        <label class="text-sm flex items-center gap-2 mb-3">
            <input type="checkbox" name="critical_window_enabled" value="1" {{ $setting->critical_window_enabled ? 'checked' : '' }}>
            <span>Aktif</span>
        </label>
        <div class="mb-3"><label class="text-xs text-default block mb-1">Uyarı Öncesi (gün)</label>
            <input type="number" name="critical_window_days" min="1" max="60" value="{{ $setting->critical_window_days }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
        <div class="mb-4"><label class="text-xs text-default block mb-1">MTV Uyarı Öncesi (gün)</label>
            <input type="number" name="mtv_reminder_days" min="1" max="180" value="{{ $setting->mtv_reminder_days }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
        <div class="text-xs text-default mb-3">Son çalıştırma: {{ $setting->critical_window_last_run_at?->format('d.m.Y H:i') ?? '—' }} · Bildirilen: {{ $setting->critical_window_last_run_notified ?? 0 }}</div>
        <button class="btn-sm bg-primary text-white">{{ __('Save') }}</button>
    </form>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="font-semibold mb-3">Elle Tetikle</div>
        <p class="text-sm text-default mb-3">Zamanlanmış görevleri beklemeden kritik pencere kontrolünü şimdi çalıştır.</p>
        @can('run critical window')
            <form method="POST" action="{{ route('app.fleet.settings.tasks.run-critical-window') }}">
                @csrf
                <button class="btn-sm bg-warning text-white">Kritik Pencere Kontrolünü Çalıştır</button>
            </form>
        @endcan
    </div>
</div>
@endsection

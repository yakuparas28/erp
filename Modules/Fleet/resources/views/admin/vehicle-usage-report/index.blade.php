@extends('app.layouts.app')

@section('title', __('Usage Report'))

@section('content')
<h1 class="text-xl font-bold mb-4">{{ __('Usage Report') }}</h1>

<form method="GET" class="bg-white border border-border-color rounded-md p-4 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div><label class="text-xs text-default">{{ __('From') }}</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
        <div><label class="text-xs text-default">{{ __('To') }}</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
        <div><button class="btn-sm bg-primary text-white w-full">{{ __('Filter') }}</button></div>
    </div>
</form>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Toplam Rezervasyon</div><div class="text-2xl font-bold">{{ $summary['toplam_rezervasyon'] }}</div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Toplam KM</div><div class="text-2xl font-bold">{{ number_format($summary['toplam_km']) }}</div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Toplam Gün</div><div class="text-2xl font-bold">{{ $summary['toplam_gun'] }}</div></div>
</div>

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-border-color text-left">
            <tr>
                <th class="p-3">{{ __('Vehicle') }}</th>
                <th class="p-3">Rezervasyon</th>
                <th class="p-3">Toplam KM</th>
                <th class="p-3">Toplam Gün</th>
                <th class="p-3">KM / Gün</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-border-color">
                    <td class="p-3 font-semibold">{{ $row['vehicle']->plaka }} <span class="text-xs text-default">{{ $row['vehicle']->marka_model }}</span></td>
                    <td class="p-3">{{ $row['reservation_count'] }}</td>
                    <td class="p-3">{{ number_format($row['toplam_km']) }}</td>
                    <td class="p-3">{{ $row['toplam_gun'] }}</td>
                    <td class="p-3">{{ $row['km_gun_ortalama'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-default">{{ __('No records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

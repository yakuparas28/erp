@extends('app.layouts.app')

@section('title', __('Maintenance Records'))

@section('content')
<h1 class="text-xl font-bold mb-4">{{ __('Maintenance Records') }}</h1>

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-border-color text-left">
            <tr>
                <th class="p-3">{{ __('Vehicle') }}</th>
                <th class="p-3">Tip</th>
                <th class="p-3">Yapılan İşlemler</th>
                <th class="p-3">Sonraki Bakım</th>
                <th class="p-3">Sonraki Muayene</th>
                <th class="p-3">Giren</th>
                <th class="p-3">Tarih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $r)
                <tr class="border-b border-border-color">
                    <td class="p-3 font-semibold">{{ $r->vehicle?->plaka }}</td>
                    <td class="p-3">{{ $r->islem_turu }}</td>
                    <td class="p-3 text-xs">{{ Str::limit($r->yapilan_islemler, 60) }}</td>
                    <td class="p-3">{{ $r->yeni_bakim_tarihi?->format('d.m.Y') ?? '—' }}</td>
                    <td class="p-3">{{ $r->yeni_muayene_tarihi?->format('d.m.Y') ?? '—' }}</td>
                    <td class="p-3">{{ $r->girisYapan?->name }}</td>
                    <td class="p-3 text-xs text-default">{{ $r->kayit_tarihi?->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-center text-default">{{ __('No records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $records->links() }}</div>
@endsection

@extends('app.layouts.app')

@section('title', __('Fleet Calendar'))

@section('content')
@php
    // Hücre durumu: bloke > aktif > bekleyen > boş.
    // Renkler proje CSS'inde tanımlı `bg-*-100 text-* border-*` utility'leriyle
    // ifade edildi (arbitrary tailwind sınıfları precompiled stylesheet'te yok).
    $cellFor = function ($vehicle, $day) use ($reservations, $blocks) {
        $dStr = $day->toDateString();
        $blok = ($blocks[$vehicle->id] ?? collect())->first(fn ($b) => $dStr >= $b->start_date->toDateString() && $dStr <= $b->end_date->toDateString());
        if ($blok) {
            $iconMap = [
                'bakim' => 'ph-wrench',
                'muayene' => 'ph-clipboard-text',
                'bloke' => 'ph-lock',
            ];
            $labelMap = ['bakim' => 'Bakım', 'muayene' => 'Muayene', 'bloke' => 'Bloke'];
            $tur = $labelMap[$blok->block_type] ?? 'Bloke';

            return [
                'type' => $blok->block_type,
                'label' => $tur,
                'icon' => $iconMap[$blok->block_type] ?? 'ph-lock',
                'class' => 'bg-danger-100 text-danger border-danger',
                'tip' => "{$vehicle->plaka} — ".$day->format('d.m.Y').' · '.$tur.($blok->aciklama ? ': '.$blok->aciklama : ''),
            ];
        }

        $rez = ($reservations[$vehicle->id] ?? collect())->first(fn ($r) => $dStr >= $r->planlanan_alis_at->toDateString() && $dStr <= $r->planlanan_teslim_at->toDateString());
        if ($rez) {
            if ($rez->onay_durumu === 'Onaylandi') {
                return [
                    'type' => 'aktif',
                    'label' => 'Kullanımda',
                    'icon' => 'ph-car',
                    'class' => 'fleet-cell-aktif',
                    'tip' => "{$vehicle->plaka} — ".$day->format('d.m.Y').' · Kullanımda: '.($rez->aktifSofor?->name ?? '—').($rez->project ? ' ('.$rez->project->ad.')' : ''),
                ];
            }

            return [
                'type' => 'bekliyor',
                'label' => 'Onay bekliyor',
                'icon' => 'ph-hourglass',
                'class' => 'bg-warning-100 text-warning border-warning',
                'tip' => "{$vehicle->plaka} — ".$day->format('d.m.Y').' · Onay bekliyor: '.($rez->aktifSofor?->name ?? '—'),
            ];
        }

        return [
            'type' => 'bos',
            'label' => 'Garajda',
            'icon' => '',
            'class' => 'bg-success-100 text-success border-success',
            'tip' => "{$vehicle->plaka} — ".$day->format('d.m.Y').' · Garajda',
        ];
    };

    $days = [];
    for ($d = $start->copy(); $d->lte($end); $d = $d->addDay()) { $days[] = $d; }
    $prev = $start->subMonth();
    $next = $start->addMonth();
    $todayStr = today()->toDateString();
@endphp

<style>
    .fleet-cal-cell { min-width: 30px; height: 32px; padding: 2px; border: 1px solid; font-size: 11px; line-height: 1; }
    .fleet-cell-aktif { background:#e2e8f0; color:#334155; border-color:#cbd5e1; }
    .fleet-cell-reservable { cursor: pointer; }
    .fleet-cell-reservable:hover { outline: 2px solid #2563eb; outline-offset: -2px; }
    .fleet-cell-selected { background-color: #bfdbfe !important; color: #1e3a8a !important; font-weight: bold; border-color: #60a5fa !important; }
    .fleet-cell-selected-range { background-color: #dbeafe !important; color: #1d4ed8 !important; border-color: #93c5fd !important; }
    .fleet-cal-day-head { min-width: 30px; padding: 4px 2px; font-size: 11px; }
    .fleet-cal-today-head { background: #dbeafe; color: #1d4ed8; font-weight: bold; }
    .fleet-cal-weekend-head { color: #9ca3af; }
    .fleet-cal-sticky { position: sticky; left: 0; z-index: 2; background: white; }
    .fleet-cal-sticky-head { position: sticky; left: 0; z-index: 3; background: #f9fafb; }
    /* Legend swatch renkleri (compiled stylesheet'e uygun) */
    .fleet-legend-garajda { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .fleet-legend-aktif { background:#e2e8f0; color:#334155; border:1px solid #cbd5e1; }
    .fleet-legend-blokeli { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
    .fleet-legend-bekliyor { background:#fef3c7; color:#b45309; border:1px solid #fcd34d; }
</style>

<div class="bg-white border border-border-color rounded-md">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-border-color">
        <div>
            <h1 class="text-gray-900 text-lg font-bold flex items-center gap-2"><i class="ph ph-calendar"></i> {{ __('Fleet Calendar') }}</h1>
            <div class="text-xs text-default mt-0.5">{{ $start->translatedFormat('F Y') }} — {{ __('Read-only monthly view') }}</div>
        </div>
        <div class="inline-flex items-center gap-1">
            <a href="{{ route('app.fleet.calendar.index', ['year' => $prev->year, 'month' => $prev->month]) }}" class="btn-sm border border-border-color inline-flex items-center justify-center px-2 py-1"><i class="ph ph-caret-left"></i></a>
            <a href="{{ route('app.fleet.calendar.index') }}" class="btn-sm border border-border-color px-3 py-1">{{ __('Today') }}</a>
            <a href="{{ route('app.fleet.calendar.index', ['year' => $next->year, 'month' => $next->month]) }}" class="btn-sm border border-border-color inline-flex items-center justify-center px-2 py-1"><i class="ph ph-caret-right"></i></a>
        </div>
    </div>

    @if (session('success'))
        <div class="bg-success-transparent text-success border-b border-success px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="bg-danger-transparent text-danger border-b border-danger px-4 py-3 text-sm">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if ($canReserve)
        <div class="bg-info-transparent border-b border-info text-info px-4 py-2 text-sm flex items-center justify-between gap-3" data-selection-info>
            <span><i class="ph ph-hand-pointing"></i> <strong>Hızlı rezervasyon:</strong> Boş (yeşil) bir hücreye tıklayın — 1. tık <strong>alış</strong>, 2. tık <strong>teslim</strong> tarihini belirler.</span>
            <button type="button" class="btn-sm border border-border-color bg-white px-2 py-1 hidden" data-clear-selection><i class="ph ph-x"></i> Seçimi Temizle</button>
        </div>
    @endif

    <div class="overflow-x-auto" style="max-width:100%;">
        <table class="w-full text-center border-collapse" style="min-width: 900px;">
            <thead class="bg-gray-50">
                <tr>
                    <th class="fleet-cal-sticky-head text-left p-2 text-xs" style="min-width:180px;">{{ __('Plate') }}</th>
                    @foreach ($days as $d)
                        <th class="fleet-cal-day-head @if ($d->toDateString() === $todayStr) fleet-cal-today-head @elseif ($d->isWeekend()) fleet-cal-weekend-head @endif">{{ $d->day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($vehicles as $v)
                    <tr class="border-t border-border-color">
                        <td class="fleet-cal-sticky text-left p-2 border-r border-border-color">
                            <div class="font-semibold text-sm">{{ $v->plaka }}</div>
                            <div class="text-[10px] text-default">{{ $v->marka_model }}</div>
                        </td>
                        @foreach ($days as $d)
                            @php
                                $cell = $cellFor($v, $d);
                                $isPast = $d->copy()->startOfDay()->lt(today());
                                $reservable = $canReserve && $cell['type'] === 'bos' && ! $isPast;
                                $extraCls = $reservable ? ' fleet-cell-reservable' : '';
                            @endphp
                            <td class="fleet-cal-cell {{ $cell['class'] }}{{ $extraCls }}"
                                title="{{ $cell['tip'] }}{{ $reservable ? ' · Tıkla: rezervasyon başlat' : '' }}"
                                @if ($reservable)
                                    data-vehicle-id="{{ $v->id }}"
                                    data-vehicle-plaka="{{ $v->plaka }}"
                                    data-vehicle-marka="{{ $v->marka_model }}"
                                    data-date="{{ $d->toDateString() }}"
                                @endif
                            >
                                @if ($cell['icon']) <i class="ph {{ $cell['icon'] }}"></i> @else &nbsp; @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($days) + 1 }}" class="p-6 text-center text-default"><i class="ph ph-car"></i> Henüz kayıtlı araç yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-border-color bg-light-500 px-4 py-3 flex flex-wrap gap-4 text-xs text-default items-center">
        <strong class="text-title">Renk kodu:</strong>
        <span class="inline-flex items-center gap-1"><span class="inline-flex items-center justify-center w-6 h-6 rounded fleet-legend-garajda text-[10px]"><i class="ph ph-check"></i></span> Garajda</span>
        <span class="inline-flex items-center gap-1"><span class="inline-flex items-center justify-center w-6 h-6 rounded fleet-legend-aktif text-[10px]"><i class="ph ph-car"></i></span> Kullanımda</span>
        <span class="inline-flex items-center gap-1"><span class="inline-flex items-center justify-center w-6 h-6 rounded fleet-legend-blokeli text-[10px]"><i class="ph ph-wrench"></i></span> Bakımda / Blokeli</span>
        <span class="inline-flex items-center gap-1"><span class="inline-flex items-center justify-center w-6 h-6 rounded fleet-legend-bekliyor text-[10px]"><i class="ph ph-hourglass"></i></span> Onay bekliyor</span>
        <span class="ms-auto text-[10px] text-default"><i class="ph ph-info"></i> Hücre üzerine gelin: plaka, tarih, şoför/proje bilgisi görünür.</span>
    </div>
</div>

@if ($canReserve)
<button type="button" id="fleet-reserve-trigger" data-hs-overlay="#fleet-reserve-modal" class="hidden">open</button>
<div id="fleet-reserve-modal" class="hs-overlay hidden fixed top-0 start-0 w-full h-full z-[70] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="opacity-0 transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto flex items-center min-h-[calc(100%-56px)]">
        <div class="w-full bg-white border rounded-xl pointer-events-auto shadow-lg">
            <div class="flex justify-between items-center py-3 px-4 border-b bg-primary-transparent">
                <h3 class="font-bold text-primary flex items-center gap-2"><i class="ph ph-calendar-plus"></i> Araç Rezervasyonu</h3>
                <button type="button" data-hs-overlay="#fleet-reserve-modal" class="size-8 inline-flex items-center justify-center rounded-full bg-white border"><i class="ph ph-x"></i></button>
            </div>
            <form method="POST" action="{{ route('app.fleet.calendar.reserve') }}" id="fleet-reserve-form">
                @csrf
                <input type="hidden" name="arac_id" data-input="arac_id">
                <input type="hidden" name="planlanan_alis_at" data-input="planlanan_alis_at">
                <input type="hidden" name="planlanan_teslim_at" data-input="planlanan_teslim_at">
                <input type="hidden" name="__year" value="{{ $year }}">
                <input type="hidden" name="__month" value="{{ $month }}">
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <div>
                            <label class="text-xs text-default">Araç</label>
                            <div class="p-2 bg-light-500 rounded text-sm font-medium" data-display="arac">—</div>
                        </div>
                        <div>
                            <label class="text-xs text-default">Alış Tarihi</label>
                            <div class="p-2 bg-success-transparent text-success rounded text-sm font-medium" data-display="alis">—</div>
                        </div>
                        <div>
                            <label class="text-xs text-default">Teslim Tarihi</label>
                            <div class="p-2 bg-warning-transparent text-warning rounded text-sm font-medium" data-display="teslim">—</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-default">Proje <span class="text-danger">*</span></label>
                            <select name="proje_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white">
                                <option value="">Proje seçin...</option>
                                @foreach ($projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->ad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-default">Alış Saati</label>
                            <input type="time" value="09:00" data-input-time="alis" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white">
                            <div class="text-[10px] text-default mt-1">Teslim saati 17:00 varsayılır</div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-default">Ek Şoförler <span class="text-default">(opsiyonel)</span></label>
                            <select name="ek_soforler[]" multiple size="4" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white">
                                @foreach ($drivers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <div class="text-[10px] text-default mt-1">Ctrl/Cmd ile çoklu seçim</div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 py-3 px-4 border-t bg-gray-50">
                    <button type="button" data-hs-overlay="#fleet-reserve-modal" class="btn-sm border border-border-color">İptal</button>
                    <button class="btn-sm bg-primary text-white inline-flex items-center gap-1"><i class="ph ph-paper-plane-tilt"></i> Talebi Gönder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modalEl = document.getElementById('fleet-reserve-modal');
    if (!modalEl) return;
    const form = document.getElementById('fleet-reserve-form');
    const selectionInfo = document.querySelector('[data-selection-info]');
    const clearBtn = document.querySelector('[data-clear-selection]');
    const state = { pickup: null, delivery: null };

    const clearSelection = () => {
        state.pickup = null;
        state.delivery = null;
        document.querySelectorAll('.fleet-cell-selected, .fleet-cell-selected-range')
            .forEach(el => el.classList.remove('fleet-cell-selected', 'fleet-cell-selected-range'));
        if (clearBtn) clearBtn.classList.add('hidden');
    };

    const highlightRange = () => {
        if (!state.pickup || !state.delivery) return;
        document.querySelectorAll('.fleet-cell-reservable').forEach(el => {
            if (Number(el.dataset.vehicleId) !== state.pickup.vehicleId) return;
            if (el.dataset.date > state.pickup.date && el.dataset.date < state.delivery.date) {
                el.classList.add('fleet-cell-selected-range');
            }
        });
    };

    const openModal = () => {
        const alisTime = form.querySelector('[data-input-time="alis"]').value || '09:00';
        form.querySelector('[data-input="arac_id"]').value = state.pickup.vehicleId;
        form.querySelector('[data-input="planlanan_alis_at"]').value = state.pickup.date + ' ' + alisTime + ':00';
        form.querySelector('[data-input="planlanan_teslim_at"]').value = state.delivery.date + ' 17:00:00';
        modalEl.querySelector('[data-display="arac"]').textContent = state.pickup.plaka + ' — ' + state.pickup.marka;
        const fmt = (d) => new Date(d).toLocaleDateString('tr-TR', {day:'2-digit', month:'long', year:'numeric'});
        modalEl.querySelector('[data-display="alis"]').textContent = fmt(state.pickup.date);
        modalEl.querySelector('[data-display="teslim"]').textContent = fmt(state.delivery.date);
        document.getElementById('fleet-reserve-trigger')?.click();
    };

    document.querySelectorAll('.fleet-cell-reservable').forEach(cell => {
        cell.addEventListener('click', () => {
            const v = { id: Number(cell.dataset.vehicleId), plaka: cell.dataset.vehiclePlaka, marka: cell.dataset.vehicleMarka };
            const d = cell.dataset.date;
            if (!state.pickup) {
                clearSelection();
                state.pickup = { vehicleId: v.id, plaka: v.plaka, marka: v.marka, date: d };
                cell.classList.add('fleet-cell-selected');
                if (clearBtn) clearBtn.classList.remove('hidden');
                return;
            }
            if (v.id !== state.pickup.vehicleId || d <= state.pickup.date) {
                clearSelection();
                state.pickup = { vehicleId: v.id, plaka: v.plaka, marka: v.marka, date: d };
                cell.classList.add('fleet-cell-selected');
                if (clearBtn) clearBtn.classList.remove('hidden');
                return;
            }
            state.delivery = { vehicleId: v.id, date: d };
            cell.classList.add('fleet-cell-selected');
            highlightRange();
            openModal();
        });
    });

    if (clearBtn) clearBtn.addEventListener('click', clearSelection);

    const timeInput = form.querySelector('[data-input-time="alis"]');
    timeInput?.addEventListener('change', () => {
        if (state.pickup && state.delivery) {
            form.querySelector('[data-input="planlanan_alis_at"]').value = state.pickup.date + ' ' + timeInput.value + ':00';
        }
    });

    modalEl.addEventListener('close.hs.overlay', () => {
        const sel = form.querySelector('select[name="proje_id"]');
        if (sel) sel.value = '';
        const ek = form.querySelector('select[name="ek_soforler[]"]');
        if (ek) Array.from(ek.options).forEach(o => o.selected = false);
    });
})();
</script>
@endif
@endsection

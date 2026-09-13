<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Fleet\Services\VehicleUsageReportService;

class VehicleUsageReportController extends Controller
{
    public function index(Request $request, VehicleUsageReportService $service): View
    {
        $from = CarbonImmutable::parse($request->input('from', now()->startOfMonth()->toDateString()));
        $to = CarbonImmutable::parse($request->input('to', now()->endOfMonth()->toDateString()))->endOfDay();
        $rows = $service->report($from, $to);

        $summary = [
            'toplam_km' => $rows->sum('toplam_km'),
            'toplam_gun' => $rows->sum('toplam_gun'),
            'toplam_rezervasyon' => $rows->sum('reservation_count'),
        ];

        return view('fleet::admin.vehicle-usage-report.index', compact('rows', 'summary', 'from', 'to'));
    }
}

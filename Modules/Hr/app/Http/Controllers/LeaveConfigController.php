<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\CriticalDate;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Holiday;
use Modules\Hr\Models\LeaveHourConfig;

/**
 * "İzin Ayarları" tek sayfa: resmi tatiller, kritik tarihler, saatlik izin
 * konfigürasyonu (default + departman override) burada yönetilir.
 */
class LeaveConfigController extends Controller
{
    public function index(): View
    {
        return view('hr::leaves.config', [
            'holidays' => Holiday::orderBy('date')->get(),
            'criticalDates' => CriticalDate::with('creator')->orderBy('start_date')->get(),
            'hourConfigs' => LeaveHourConfig::with('department')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:100'],
            'is_recurring_yearly' => ['sometimes', 'boolean'],
        ]);

        Holiday::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id, 'date' => $validated['date']],
            $validated,
        );

        return back()->with('status', __('Holiday added.'));
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('status', __('Holiday deleted.'));
    }

    public function storeCriticalDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'block_leave_requests' => ['sometimes', 'boolean'],
        ]);

        CriticalDate::create($validated + [
            'created_by' => $request->user()->id,
            'scope' => 'tenant_wide',
        ]);

        return back()->with('status', __('Critical date added.'));
    }

    public function destroyCriticalDate(CriticalDate $criticalDate): RedirectResponse
    {
        $criticalDate->delete();

        return back()->with('status', __('Critical date deleted.'));
    }

    public function storeHourConfig(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'daily_work_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'monthly_leave_hours' => ['required', 'numeric', 'min:0'],
            'min_hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'negative_balance_policy' => ['required', Rule::in(['strict', 'lenient'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        LeaveHourConfig::updateOrCreate(
            ['tenant_id' => $tenantId, 'department_id' => $validated['department_id'] ?? null],
            $validated,
        );

        return back()->with('status', __('Hourly leave settings saved.'));
    }

    public function destroyHourConfig(LeaveHourConfig $leaveHourConfig): RedirectResponse
    {
        $leaveHourConfig->delete();

        return back()->with('status', __('Hourly setting deleted.'));
    }

    /**
     * "Mazeret İzni Yapılandırması" — demo VatPortal / Spec v4.1 §9.5:
     * sol tarafta tenant geneli tek row, sağ tarafta departman override tablosu.
     */
    public function hourlyIndex(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $platform = LeaveHourConfig::firstOrCreate(
            ['tenant_id' => $tenantId, 'department_id' => null],
            [
                'daily_work_hours' => 8,
                'monthly_leave_hours' => 24,
                'min_hours' => 1,
                'negative_balance_policy' => 'strict',
                'is_active' => true,
            ],
        );

        return view('hr::leaves.hourly-config', [
            'platform' => $platform,
            'overrides' => LeaveHourConfig::with('department')
                ->whereNotNull('department_id')
                ->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function updatePlatformHourly(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'min_hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'daily_work_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'negative_balance_policy' => ['required', Rule::in(['strict', 'lenient'])],
        ]);

        LeaveHourConfig::updateOrCreate(
            ['tenant_id' => $tenantId, 'department_id' => null],
            $validated + ['monthly_leave_hours' => 24],
        );

        return back()->with('status', __('Platform-wide settings saved.'));
    }

    public function storeHourOverride(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
                Rule::unique('leave_hour_configs', 'department_id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'min_hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
        ]);

        $platform = LeaveHourConfig::where('tenant_id', $tenantId)->whereNull('department_id')->first();

        LeaveHourConfig::create([
            'tenant_id' => $tenantId,
            'department_id' => $validated['department_id'],
            'min_hours' => $validated['min_hours'],
            'daily_work_hours' => $platform?->daily_work_hours ?? 8,
            'monthly_leave_hours' => $platform?->monthly_leave_hours ?? 24,
            'negative_balance_policy' => $platform?->negative_balance_policy ?? 'strict',
            'is_active' => true,
        ]);

        return back()->with('status', __('Department override added.'));
    }

    public function destroyHourOverride(LeaveHourConfig $leaveHourConfig): RedirectResponse
    {
        abort_if($leaveHourConfig->department_id === null, 422, __('Platform-wide row cannot be deleted; edit it instead.'));
        $leaveHourConfig->delete();

        return back()->with('status', __('Department override removed.'));
    }
}

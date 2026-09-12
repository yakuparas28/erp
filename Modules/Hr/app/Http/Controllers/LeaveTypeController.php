<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\LeaveType;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        return view('hr::leaves.types', [
            'types' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        LeaveType::create($validated);

        return redirect()->route('app.hr.leave-types.index')->with('status', __(':name added.', ['name' => $validated['name']]));
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $validated = $this->validated($request, $leaveType);
        $leaveType->update($validated);

        return redirect()->route('app.hr.leave-types.index')->with('status', __(':name updated.', ['name' => $leaveType->name]));
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        abort_if($leaveType->requests()->exists(), 422, __('Cannot delete a leave type that has requests.'));
        $name = $leaveType->name;
        $leaveType->delete();

        return redirect()->route('app.hr.leave-types.index')->with('status', __(':name deleted.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LeaveType $leaveType = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'key' => [
                'required', 'string', 'max:40',
                Rule::unique('leave_types', 'key')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($leaveType?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', Rule::in(['day', 'hour', 'half_day'])],
            'max_days_per_year' => ['nullable', 'integer', 'min:0'],
            'deducts_from_balance' => ['sometimes', 'boolean'],
            'requires_document' => ['sometimes', 'boolean'],
            'requires_second_level' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

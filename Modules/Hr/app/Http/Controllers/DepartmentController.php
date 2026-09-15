<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Employee;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('hr::departments.index', [
            'departments' => Department::with(['parent', 'manager'])
                ->withCount(['employees', 'children'])
                ->orderBy('name')->get(),
            'employees' => Employee::where('is_active', true)->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $department = Department::create($validated);

        return redirect()->route('app.hr.departments.index')
            ->with('status', __(':name added.', ['name' => $department->name]));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $this->validated($request, $department);

        $department->update($validated);

        return redirect()->route('app.hr.departments.index')
            ->with('status', __(':name updated.', ['name' => $department->name]));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $name = $department->name;
        $department->delete();

        return redirect()->route('app.hr.departments.index')
            ->with('status', __(':name deleted.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
                Rule::notIn([$department?->id]),
            ],
            'manager_employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

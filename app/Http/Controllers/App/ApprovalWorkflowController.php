<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Core Approval motorunun tenant tarafı yönetim ekranı: workflow'lar ve
 * step'ler burada oluşturulur. Modül-bağımsız kaldığı için app namespace
 * altında; İK/Filo/... her modülün kendi subject_type'ıyla eşleşen bir
 * workflow tanımlanır.
 */
class ApprovalWorkflowController extends Controller
{
    /** @var array<string, string> */
    private array $subjectTypes = [
        'leave_request' => 'İzin Talebi (HR)',
        'expense' => 'Masraf Talebi (Masraflar)',
        'quotation' => 'Teklif Gönderimi (Satış)',
        'sales_order' => 'Satış Siparişi Onayı',
        'purchase_order' => 'Satın Alma Siparişi Onayı',
        'invoice' => 'Fatura Onayı (Muhasebe)',
    ];

    public function index(): View
    {
        return view('app.approvals.index', [
            'workflows' => ApprovalWorkflow::withCount('steps')->orderBy('name')->get(),
            'subjectTypes' => $this->subjectTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'subject_type' => ['required', Rule::in(array_keys($this->subjectTypes))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ApprovalWorkflow::create($validated);

        return redirect()->route('app.approval-workflows.index')->with('status', __(':name added.', ['name' => $validated['name']]));
    }

    public function update(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'subject_type' => ['required', Rule::in(array_keys($this->subjectTypes))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $workflow->update($validated);

        return redirect()->route('app.approval-workflows.show', $workflow)->with('status', __(':name updated.', ['name' => $workflow->name]));
    }

    public function destroy(ApprovalWorkflow $workflow): RedirectResponse
    {
        $name = $workflow->name;
        $workflow->delete();

        return redirect()->route('app.approval-workflows.index')->with('status', __(':name deleted.', ['name' => $name]));
    }

    public function show(ApprovalWorkflow $workflow): View
    {
        return view('app.approvals.show', [
            'workflow' => $workflow->load('steps'),
            'subjectTypes' => $this->subjectTypes,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function storeStep(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1', Rule::unique('approval_workflow_steps', 'sequence')->where(fn ($q) => $q->where('approval_workflow_id', $workflow->id))],
            'approver_type' => ['required', Rule::in(['manager', 'department_manager', 'role', 'user'])],
            'approver_value' => ['nullable', 'string', 'max:100'],
        ]);

        if (in_array($validated['approver_type'], ['role', 'user'], true)) {
            abort_if(empty($validated['approver_value']), 422, __('Approver value is required for role/user types.'));
        }

        $workflow->steps()->create($validated);

        return redirect()->route('app.approval-workflows.show', $workflow)->with('status', __('Step added.'));
    }

    public function destroyStep(ApprovalWorkflow $workflow, ApprovalWorkflowStep $step): RedirectResponse
    {
        abort_if($step->approval_workflow_id !== $workflow->id, 404);
        $step->delete();

        return redirect()->route('app.approval-workflows.show', $workflow)->with('status', __('Step removed.'));
    }
}

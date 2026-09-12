<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\ConsumptionRule;

/**
 * Demo'daki "Tüketim / Hakediş Kuralları" ekranının karşılığı: izin
 * bakiye motorunun uyacağı yasal/politika kurallarının tenant düzeyinde
 * kataloğu (BR-IT-* kodları). İki kategori: 'consumption' (bakiye
 * tüketimi) ve 'accrual' (hakediş). Bu ekran kuralların aktif/pasif
 * yönetimini + yasal dayanak metni gösterimini üstlenir; iş mantığına
 * doğrudan bağlanma bir sonraki adımda LeaveRequestService'te yapılır.
 */
class ConsumptionRuleController extends Controller
{
    public function index(): View
    {
        return view('hr::leaves.consumption-rules', [
            'rules' => ConsumptionRule::orderBy('category')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        ConsumptionRule::create($validated);

        return redirect()->route('app.hr.consumption-rules.index')->with('status', __(':name added.', ['name' => $validated['name']]));
    }

    public function update(Request $request, ConsumptionRule $consumptionRule): RedirectResponse
    {
        $validated = $this->validated($request, $consumptionRule);
        $consumptionRule->update($validated);

        return redirect()->route('app.hr.consumption-rules.index')->with('status', __(':name updated.', ['name' => $consumptionRule->name]));
    }

    public function destroy(ConsumptionRule $consumptionRule): RedirectResponse
    {
        $name = $consumptionRule->name;
        $consumptionRule->delete();

        return redirect()->route('app.hr.consumption-rules.index')->with('status', __(':name deleted.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ConsumptionRule $rule = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('consumption_rules', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($rule?->id),
            ],
            'category' => ['required', Rule::in(['consumption', 'accrual'])],
            'name' => ['required', 'string', 'max:200'],
            'legal_basis' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\PosTerminal;

class PosTerminalController extends Controller
{
    public function index(): View
    {
        return view('accounting::pos-terminals.index', [
            'terminals' => PosTerminal::with('bankJournal')->orderByDesc('is_active')->orderBy('name')->get(),
            'bankJournals' => Journal::where('type', 'bank')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['commission_rates'] = $this->parseRates($validated['commission_rates_raw'] ?? '');
        unset($validated['commission_rates_raw']);

        $terminal = new PosTerminal($validated);
        $terminal->tenant_id = $request->user()->tenant_id;
        $terminal->save();

        return back()->with('status', __(':name added.', ['name' => $terminal->name]));
    }

    public function update(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $validated = $this->validated($request, $terminal);
        $validated['commission_rates'] = $this->parseRates($validated['commission_rates_raw'] ?? '');
        unset($validated['commission_rates_raw']);

        $terminal->update($validated);

        return back()->with('status', __(':name updated.', ['name' => $terminal->name]));
    }

    public function destroy(PosTerminal $terminal): RedirectResponse
    {
        if (CardPayment::where('pos_terminal_id', $terminal->id)->exists()) {
            $terminal->update(['is_active' => false]);

            return back()->with('status', __(':name archived (has activity, cannot be deleted).', ['name' => $terminal->name]));
        }

        $terminal->delete();

        return back()->with('status', __(':name deleted.', ['name' => $terminal->name]));
    }

    /**
     * "1:1.5,3:2.5,6:3.5" gibi virgüllü string → ["1"=>"1.5","3"=>"2.5",...]
     *
     * @return array<string,string>
     */
    private function parseRates(string $raw): array
    {
        $out = [];
        foreach (explode(',', trim($raw)) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            [$k, $v] = array_pad(explode(':', $chunk, 2), 2, null);
            $k = trim((string) $k);
            $v = trim((string) $v);
            if ($k !== '' && $v !== '' && is_numeric($k) && is_numeric($v)) {
                $out[(string) (int) $k] = $v;
            }
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?PosTerminal $existing = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'bank_journal_id' => [
                'required',
                Rule::exists('journals', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('type', 'bank')),
            ],
            'settlement_days' => ['required', 'integer', 'min:0', 'max:60'],
            'commission_rates_raw' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

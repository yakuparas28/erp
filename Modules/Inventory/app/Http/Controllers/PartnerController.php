<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Inventory\Http\Requests\PartnerRequest;
use Modules\Inventory\Models\Partner;

class PartnerController extends Controller
{
    public function index(): View
    {
        return view('inventory::partners.index', [
            'partners' => Partner::orderBy('name')->get(),
        ]);
    }

    public function store(PartnerRequest $request): RedirectResponse
    {
        $partner = Partner::create($request->normalizedData());

        return redirect()->route('app.inventory.partners.index')
            ->with('status', __(':name added.', ['name' => $partner->name]));
    }

    public function update(PartnerRequest $request, Partner $partner): RedirectResponse
    {
        $partner->update($request->normalizedData());

        return redirect()->route('app.inventory.partners.index')
            ->with('status', __(':name updated.', ['name' => $partner->name]));
    }
}

<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Fleet\Models\Project;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::orderByDesc('aktif')->orderBy('ad')->paginate(30);

        return view('fleet::admin.projects.index', compact('projects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ad' => ['required', 'string', 'max:200'],
            'baslangic_tarihi' => ['nullable', 'date'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $data['aktif'] = (bool) ($data['aktif'] ?? true);
        Project::create($data);

        return back()->with('success', 'Proje kaydedildi.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'ad' => ['required', 'string', 'max:200'],
            'baslangic_tarihi' => ['nullable', 'date'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $data['aktif'] = (bool) ($data['aktif'] ?? false);
        $project->update($data);

        return back()->with('success', 'Proje güncellendi.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return back()->with('success', 'Proje silindi.');
    }
}

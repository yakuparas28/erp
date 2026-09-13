@extends('app.layouts.app')

@section('title', __('Projects'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-bold">{{ __('Projects') }}</h1>
    <button type="button" data-hs-overlay="#project-modal" class="btn-sm bg-dark text-white"><i class="ph ph-plus"></i> {{ __('New') }}</button>
</div>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-border-color text-left">
            <tr>
                <th class="p-3">{{ __('Name') }}</th>
                <th class="p-3">{{ __('Start Date') }}</th>
                <th class="p-3">{{ __('Status') }}</th>
                <th class="p-3 text-right">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $p)
                <tr class="border-b border-border-color">
                    <td class="p-3 font-semibold">{{ $p->ad }}</td>
                    <td class="p-3">{{ $p->baslangic_tarihi?->format('d.m.Y') ?? '—' }}</td>
                    <td class="p-3">
                        <span class="text-xs px-2 py-0.5 rounded {{ $p->aktif ? 'bg-success-transparent text-success' : 'bg-gray-100 text-default' }}">{{ $p->aktif ? __('Active') : __('Inactive') }}</span>
                    </td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <button type="button" class="btn-sm border border-border-color" onclick='fillProject(@json($p))' data-hs-overlay="#project-modal"><i class="ph ph-pencil-simple"></i></button>
                        <form method="POST" action="{{ route('app.fleet.projects.destroy', $p) }}" class="inline" onsubmit="return confirm('{{ __('Delete?') }}');">
                            @csrf @method('DELETE')
                            <button class="btn-sm text-danger border border-danger"><i class="ph ph-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-6 text-center text-default">{{ __('No records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $projects->links() }}</div>

<div id="project-modal" class="hs-overlay hidden fixed top-0 start-0 w-full h-full z-[70] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto flex items-center min-h-[calc(100%-56px)]">
        <div class="w-full bg-white border rounded-xl pointer-events-auto">
            <div class="flex justify-between items-center py-3 px-4 border-b"><h3 class="font-bold">{{ __('Project') }}</h3><button type="button" data-hs-overlay="#project-modal"><i class="ph ph-x"></i></button></div>
            <form id="project-form" method="POST" action="{{ route('app.fleet.projects.store') }}">
                @csrf <input type="hidden" name="_method" value="POST">
                <div class="p-4 grid grid-cols-1 gap-3">
                    <div><label class="text-xs text-default">{{ __('Name') }}</label><input name="ad" required maxlength="200" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Start Date') }}</label><input name="baslangic_tarihi" type="date" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Description') }}</label><textarea name="aciklama" rows="3" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></textarea></div>
                    <label class="text-sm flex items-center gap-2"><input type="checkbox" name="aktif" value="1" checked> <span>{{ __('Active') }}</span></label>
                </div>
                <div class="flex justify-end gap-2 py-3 px-4 border-t"><button type="button" class="btn-sm border border-border-color" data-hs-overlay="#project-modal">{{ __('Cancel') }}</button><button class="btn-sm bg-primary text-white">{{ __('Save') }}</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function fillProject(p) {
    const f = document.getElementById('project-form');
    f.action = `{{ url('app/fleet/projects') }}/${p.id}`;
    f.querySelector('input[name="_method"]').value = 'PUT';
    f.querySelector('input[name="ad"]').value = p.ad;
    f.querySelector('input[name="baslangic_tarihi"]').value = p.baslangic_tarihi ?? '';
    f.querySelector('textarea[name="aciklama"]').value = p.aciklama ?? '';
    f.querySelector('input[name="aktif"]').checked = !!p.aktif;
}
</script>
@endsection

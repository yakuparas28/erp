@extends('app.layouts.app')

@section('title', __('Bank Statements'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Bank Statements') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Upload CSV files exported from your bank and let the system auto-match to payments. Turkish and English column headers both work.') }}
        </p>
    </div>
    <button type="button" data-bs-open-upload class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-upload"></i> {{ __('Upload Statement') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('file')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Filename') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Bank Account') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Period') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Lines') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Matched') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Uploaded') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statements as $stmt)
                    <tr class="border-b border-border-color">
                        <td class="py-2 text-gray-900">{{ $stmt->original_filename }}</td>
                        <td class="py-2 text-default">{{ optional($stmt->bankJournal)->name }}</td>
                        <td class="py-2 text-default text-[12px]">
                            {{ optional($stmt->period_start)->format('d.m.Y') }} → {{ optional($stmt->period_end)->format('d.m.Y') }}
                        </td>
                        <td class="py-2 text-right text-default">{{ $stmt->total_lines }}</td>
                        <td class="py-2 text-right">
                            <span class="text-[11px] px-2 py-0.5 rounded {{ $stmt->matchPct() === 100 ? 'bg-success-transparent text-success' : ($stmt->matchPct() >= 50 ? 'bg-warning-transparent text-warning' : 'bg-danger-transparent text-danger') }}">
                                {{ $stmt->matched_lines }} / {{ $stmt->total_lines }} (%{{ $stmt->matchPct() }})
                            </span>
                        </td>
                        <td class="py-2 text-default text-[12px]">
                            {{ optional($stmt->uploader)->name }} <br>
                            {{ $stmt->created_at?->diffForHumans() }}
                        </td>
                        <td class="py-2 text-right">
                            <a href="{{ route('app.accounting.bank-statements.show', $stmt) }}" class="text-primary text-[12px] hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-default">{{ __('No bank statements imported yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $statements->links() }}</div>
</div>

<div id="bs-upload-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-md shadow-xl w-full" style="max-width: min(520px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.accounting.bank-statements.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-upload"></i> {{ __('Upload Bank Statement') }}
                </h2>
                <button type="button" data-bs-close class="text-default hover:text-gray-900 cursor-pointer"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Bank Account') }} *</label>
                    <select name="bank_journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($bankJournals as $bj)
                            <option value="{{ $bj->id }}">{{ $bj->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('CSV File') }} *</label>
                    <input type="file" name="file" required accept=".csv,text/csv" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <p class="text-[11px] text-default mt-2">
                        {{ __('Expected columns (any order):') }}
                        <code class="bg-light px-1 rounded font-mono">tarih, açıklama, borç, alacak, bakiye, referans</code>
                        {{ __('or their English equivalents.') }}
                    </p>
                </div>
            </div>
            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-bs-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2"><i class="ph ph-upload"></i> {{ __('Import') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('bs-upload-modal');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };
    document.querySelectorAll('[data-bs-open-upload]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-bs-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
})();
</script>
@endsection

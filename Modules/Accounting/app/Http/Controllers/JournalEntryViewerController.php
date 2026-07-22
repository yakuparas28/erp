<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Accounting\Models\JournalEntry;

class JournalEntryViewerController extends Controller
{
    public function index(): View
    {
        return view('accounting::journal-entries.index', [
            'entries' => JournalEntry::with(['journal', 'lines'])->latest('entry_date')->latest('id')->get(),
        ]);
    }

    public function show(JournalEntry $entry): View
    {
        return view('accounting::journal-entries.show', [
            'entry' => $entry->load(['journal', 'lines.account']),
        ]);
    }
}

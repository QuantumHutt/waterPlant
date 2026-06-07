<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('accounting.view'), 403);

        $from = $request->input('from');
        $to = $request->input('to');

        return Inertia::render('Accounting/Index', [
            'accounts' => Account::orderBy('code')->get(),
            'journals' => JournalEntry::with('lines.account')->latest()->limit(50)->get(),
            'trialBalance' => $this->accounting->trialBalance($from, $to),
            'profitLoss' => $this->accounting->profitAndLoss($from, $to),
            'balanceSheet' => $this->accounting->balanceSheet($to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('accounting.post'), 403);

        $data = $request->validate([
            'narration' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->accounting->post($data['lines'], $data['narration'] ?? null, null, $data['entry_date'] ?? null);

        return back()->with('success', 'Journal entry posted.');
    }
}

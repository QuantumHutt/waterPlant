<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashClosing;
use App\Services\CashBookService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Cash book: drawer + bank for a day, bank deposits, end-of-day closing. */
class CashController extends Controller
{
    public function __construct(private readonly CashBookService $cash) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('cash.view'), 403);
        $date = $request->input('date', now()->toDateString());

        return Inertia::render('Cash/Index', [
            'date' => $date,
            'day' => $this->cash->day($date),
            'closings' => CashClosing::with('closer:id,name')->latest('closing_date')->limit(30)->get(),
        ]);
    }

    /** Cash → bank (deposit) or bank → cash (withdraw). */
    public function transfer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cash.transfer'), 403);
        $data = $request->validate([
            'direction' => ['required', 'in:deposit,withdraw'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $date = $data['date'] ?? now()->toDateString();
        [$from, $to] = $data['direction'] === 'deposit' ? ['cash', 'bank'] : ['bank', 'cash'];

        // Can't move more than the account holds at that point.
        $available = $this->cash->day($date)[$from]['closing'];
        if ((float) $data['amount'] > $available) {
            return back()->withErrors(['amount' => ($from === 'cash' ? 'Drawer' : 'Bank').' mein sirf '.Money::format($available).' hai.']);
        }

        $this->cash->transfer($from, $to, (float) $data['amount'], $date, $data['note'] ?? null);

        return back()->with('success', $data['direction'] === 'deposit'
            ? '✓ '.Money::format((float) $data['amount']).' bank mein jama — drawer se minus'
            : '✓ '.Money::format((float) $data['amount']).' bank se nikal kar drawer mein');
    }

    /** End of day: count the drawer and save it against what the system expects. */
    public function close(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cash.close'), 403);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $closing = $this->cash->close($data['date'], (float) $data['counted_cash'], $data['note'] ?? null);
        $diff = (float) $closing->difference;

        return back()->with('success', '✓ Day closed — '.($diff == 0.0 ? 'cash matches exactly'
            : ($diff < 0 ? 'SHORT '.Money::format(-$diff) : 'EXTRA '.Money::format($diff))));
    }
}

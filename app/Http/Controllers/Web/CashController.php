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

        $monthStart = \Illuminate\Support\Carbon::parse($date)->startOfMonth()->toDateString();

        return Inertia::render('Cash/Index', [
            'date' => $date,
            'day' => $this->cash->day($date),
            // This month so far: deposits list + totals ("kitna bank mein gaya").
            'month' => $this->cash->period($monthStart, $date),
            // Settlement view: since the last deposit, what's in the drawer, what to bank now.
            'hisaab' => $this->cash->hisaab($date),
            'history' => $this->cash->hisaabHistory(),
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

    /** Change money kept in the drawer at every hisaab. */
    public function float(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cash.transfer'), 403);
        $data = $request->validate(['float' => ['required', 'numeric', 'min:0']]);
        \App\Models\Setting::set('cash', 'drawer_float', (int) $data['float'], 'int');

        return back()->with('success', '✓ Drawer float set to '.Money::format((float) $data['float']));
    }

    /** Printable hisaab slip for one settlement period. */
    public function print(Request $request): \Illuminate\View\View
    {
        abort_unless($request->user()->can('cash.view'), 403);
        $to = $request->input('to', now()->toDateString());

        return view('cash.hisaab', [
            'hisaab' => $this->cash->hisaab($to),
            'to' => $to,
            'company' => \App\Models\Setting::allGrouped()['company'] ?? [],
            'user' => $request->user()->name,
        ]);
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

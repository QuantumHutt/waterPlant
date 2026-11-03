<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\ProfitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
        private readonly ProfitService $profit,
        private readonly \App\Services\CashBookService $cash,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        // POS staff without report access (e.g. salesman) go straight to the POS. Give a user
        // "Reports" and the dashboard opens for them.
        if (! $request->user()->can('reports.view') && $request->user()->can('sales.create')) {
            return redirect()->route('sales.index');
        }

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        // Profit is owner/manager data — only for users who can see reports.
        $canProfit = $request->user()->can('reports.view');

        return Inertia::render('Dashboard/Index', [
            'metrics' => $this->service->metrics(),
            'receivables' => $this->service->receivables(),
            'stockSales' => $this->service->stockAndSales(),
            // Drawer / bank right now (for the "Cash today" card).
            'cashToday' => $request->user()->can('cash.view') ? (function () use ($today) {
                $d = $this->cash->day($today);

                $h = $this->cash->hisaab($today);

                return ['drawer' => $d['cash']['closing'], 'bank' => $d['bank']['closing'], 'closed' => $d['closing'] !== null,
                    'float' => $h['float'], 'suggested' => $h['suggested_deposit'], 'since' => $h['since']];
            })() : null,
            'profit' => $canProfit ? [
                'today' => $this->profit->summary($today, $today),
                'month' => $this->profit->summary($monthStart, $today),
                'daily' => $this->profit->breakdown($monthStart, $today, 'day'),
                'bottles_today' => $this->profit->byBottle($today, $today),
                'bottles_month' => $this->profit->byBottle($monthStart, $today),
            ] : null,
        ]);
    }
}

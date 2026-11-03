<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\ProfitService;
use App\Support\Enums\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
        private readonly ProfitService $profit,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        // Salesman works on the POS only — business metrics stay with the owner/managers.
        if ($request->user()->hasRole(RoleName::Salesman->value)) {
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

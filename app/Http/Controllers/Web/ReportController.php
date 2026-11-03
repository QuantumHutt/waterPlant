<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ProductionBatch;
use App\Services\AccountingService;
use App\Services\ProfitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly ProfitService $profit,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        // Active sales only — returned/cancelled invoices are excluded from totals.
        $sales = Invoice::whereBetween('invoice_date', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'returned']);

        // Returns within the period (for visibility).
        $returns = \App\Models\SalesReturn::whereBetween('returned_at', [$from, $to]);

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to],
            'sales' => [
                'count' => (clone $sales)->count(),
                'total' => (float) (clone $sales)->sum(DB::raw('grand_total - returned_total')), // net of partial returns
                'received' => (float) (clone $sales)->sum('paid_total'),
                'outstanding' => (float) (clone $sales)->sum('balance'),
                'returns_count' => (clone $returns)->count(),
                'returns_total' => (float) (clone $returns)->sum('total'),
                'by_type' => (clone $sales)->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(grand_total - returned_total) as total'))
                    ->groupBy('type')->get(),
            ],
            'production' => [
                'batches' => ProductionBatch::whereBetween('production_date', [$from, $to])->count(),
                'water_litres' => (float) ProductionBatch::whereBetween('production_date', [$from, $to])->sum('filtered_water_litres'),
                'bottles' => (int) ProductionBatch::whereBetween('production_date', [$from, $to])->sum('bottles_produced'),
                'cost' => (float) ProductionBatch::whereBetween('production_date', [$from, $to])->sum('production_cost'),
            ],
            'inventory' => [
                'value' => (float) DB::table('stock_items')->selectRaw('COALESCE(SUM(quantity * avg_cost),0) as v')->value('v'),
                'low_stock' => (int) DB::table('products')
                    ->where('track_inventory', true)->where('is_active', true)
                    ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM stock_items WHERE stock_items.product_id = products.id) <= products.reorder_level')
                    ->count(),
            ],
            'bottles' => [
                'issued' => (int) DB::table('gallon_transactions')->where('type', 'issue')->sum('quantity'),
                'returned' => (int) DB::table('gallon_transactions')->where('type', 'return')->sum('quantity'),
                'active' => (int) DB::table('customer_gallon_balances')->sum('held_qty'),
                'missing' => (int) DB::table('customer_gallon_balances')->sum('missing_qty'),
                'damaged' => (int) DB::table('customer_gallon_balances')->sum('damaged_qty'),
                'deposit_balance' => (float) DB::table('customer_gallon_balances')->sum('deposit_held'),
                'ledger' => DB::table('customer_gallon_balances')
                    ->join('customers', 'customers.id', '=', 'customer_gallon_balances.customer_id')
                    ->where(fn ($q) => $q->where('held_qty', '>', 0)->orWhere('deposit_held', '>', 0))
                    ->select('customers.name', 'customer_gallon_balances.held_qty', 'customer_gallon_balances.deposit_held', 'customer_gallon_balances.missing_qty')
                    ->orderByDesc('customer_gallon_balances.held_qty')->limit(50)->get(),
            ],
            // Purchases & suppliers (what you bought, what you paid, what you still owe).
            'purchases' => [
                'count' => (int) DB::table('purchase_orders')->whereBetween('order_date', [$from, $to])->where('status', 'received')->count(),
                'total' => (float) DB::table('purchase_orders')->whereBetween('order_date', [$from, $to])->where('status', 'received')->sum('grand_total'),
                'paid' => (float) DB::table('payments')->where('direction', 'out')
                    ->where('payable_type', (new \App\Models\Supplier)->getMorphClass())
                    ->whereBetween('paid_at', [$from, $to])->sum('amount'),
                'supplier_outstanding' => (float) DB::table('suppliers')->where('current_balance', '>', 0)->sum('current_balance'),
                'by_supplier' => DB::table('suppliers')->where('current_balance', '>', 0)
                    ->orderByDesc('current_balance')->limit(20)->get(['name', 'current_balance']),
            ],
            // Udhaar actually collected in the period (cash that came in from old bills).
            'udhaar_collected' => (float) DB::table('payments')->where('direction', 'in')
                ->whereBetween('paid_at', [$from, $to])
                ->where(fn ($q) => $q->where('payable_type', (new \App\Models\Customer)->getMorphClass())->where('reference', 'like', 'RCV-%')
                    ->orWhere(fn ($q2) => $q2->where('payable_type', (new \App\Models\Invoice)->getMorphClass())
                        ->where(fn ($q3) => $q3->where('reference', 'like', 'UDH-%')
                            ->orWhereRaw('paid_at > (SELECT invoice_date FROM invoices WHERE invoices.id = payments.payable_id)'))))
                ->sum('amount'),
            // What customers owe right now (same figure as the dashboard udhaar list).
            'customer_outstanding' => (float) DB::table('customers')->where('current_balance', '>', 0)->sum('current_balance'),
            'profitLoss' => $this->accounting->profitAndLoss($from, $to),
            'grossProfit' => $this->profit->byProduct($from, $to),
            'byBottle' => $this->profit->byBottle($from, $to),
            // Operational P&L (sales − cost − expenses …) — daily rows for the range, last 12 months.
            'pnl' => $this->profit->summary($from, $to),
            'daily' => $this->profit->breakdown($from, $to, 'day'),
            'monthly' => $this->profit->breakdown(
                \Illuminate\Support\Carbon::parse($to)->subMonths(11)->startOfMonth()->toDateString(), $to, 'month',
            ),
            'topProducts' => DB::table('invoice_items')
                ->join('products', 'products.id', '=', 'invoice_items.product_id')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->whereBetween('invoices.invoice_date', [$from, $to])
                ->whereNotIn('invoices.status', ['cancelled', 'returned'])
                ->select('products.name', DB::raw('SUM(invoice_items.quantity - invoice_items.returned_qty) as qty'), DB::raw('SUM(invoice_items.line_total * (invoice_items.quantity - invoice_items.returned_qty) / NULLIF(invoice_items.quantity, 0)) as total'))
                ->groupBy('products.id', 'products.name')->orderByDesc('total')->limit(10)->get(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\ProductionBatch;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live dashboard KPIs computed from sales, production, inventory and gallon data.
 */
class DashboardService extends BaseService
{
    /** @return array<string, mixed> */
    public function metrics(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // Sales exclude cancelled/returned invoices.
        $soldToday = (float) Invoice::whereDate('invoice_date', $today)
            ->whereNotIn('status', ['cancelled', 'returned'])->sum(DB::raw('grand_total - returned_total')); // net of partial returns

        $soldMonth = (float) Invoice::whereBetween('invoice_date', [$monthStart, $today])
            ->whereNotIn('status', ['cancelled', 'returned'])->sum(DB::raw('grand_total - returned_total')); // net of partial returns

        $waterToday = (float) ProductionBatch::whereDate('production_date', $today)->sum('filtered_water_litres');

        // Same figure as the udhaar list: what customers owe per their ledger (incl. opening balances).
        $receivables = (float) \App\Models\Customer::where('current_balance', '>', 0)->sum('current_balance');

        $activeBottles = Schema::hasTable('customer_gallon_balances')
            ? (int) DB::table('customer_gallon_balances')->sum('held_qty') : 0;
        $deposits = Schema::hasTable('customer_gallon_balances')
            ? (float) DB::table('customer_gallon_balances')->sum('deposit_held') : 0;

        return [
            'todays_sales' => $this->money($soldToday, "Today's Sales"),
            'todays_production' => $this->stat((int) round($waterToday), 'L', "Today's Production"),
            'monthly_revenue' => $this->money($soldMonth, 'Monthly Revenue'),
            'low_stock_alerts' => $this->stat($this->lowStockCount(), 'items', 'Low Stock Alerts'),
            'active_bottles' => $this->stat($activeBottles, 'bottles', 'Active Bottles (with customers)'),
            'security_deposits' => $this->money($deposits, 'Security Deposits Held'),
            'outstanding_receivables' => $this->money($receivables, 'Outstanding Receivables'),
        ];
    }

    /**
     * Per sellable product: how many sold today / this month and what is left in stock.
     * Services (19L refill, cooler refill) have no stock — reported as unlimited (stock = null).
     * Packs show whole packs available from their base bottle stock.
     *
     * @return list<array<string, mixed>>
     */
    public function stockAndSales(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $sold = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereBetween('invoices.invoice_date', [$monthStart, $today])
            ->whereNotIn('invoices.status', ['cancelled', 'returned'])
            ->whereNull('invoices.deleted_at')
            ->groupBy('invoice_items.product_id')
            ->selectRaw('invoice_items.product_id,
                SUM(CASE WHEN invoices.invoice_date = ? THEN (invoice_items.quantity - invoice_items.returned_qty) ELSE 0 END) as today_qty,
                SUM(CASE WHEN invoices.invoice_date = ? THEN invoice_items.line_total * (invoice_items.quantity - invoice_items.returned_qty) / NULLIF(invoice_items.quantity, 0) ELSE 0 END) as today_amount,
                SUM((invoice_items.quantity - invoice_items.returned_qty)) as month_qty,
                SUM(invoice_items.line_total * (invoice_items.quantity - invoice_items.returned_qty) / NULLIF(invoice_items.quantity, 0)) as month_amount', [$today, $today])
            ->get()->keyBy('product_id');

        $onHand = DB::table('stock_items')->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as q')->pluck('q', 'product_id');

        $bottleSku = \App\Models\Setting::get('sales', 'bottle_19l_sku') ?? '19L-BOTTLE';

        return \App\Models\Product::where('is_active', true)
            ->where(fn ($q) => $q->where('sale_price', '>', 0)->orWhere('is_service', true)->orWhere('sku', $bottleSku))
            ->orderByDesc('is_service')->orderBy('name')
            ->get(['id', 'name', 'sku', 'is_service', 'pack_size', 'pack_of_product_id', 'track_inventory'])
            ->map(function ($p) use ($sold, $onHand, $bottleSku) {
                $s = $sold[$p->id] ?? null;
                $stock = match (true) {
                    (bool) $p->is_service => null,  // unlimited
                    $p->pack_of_product_id !== null => (int) floor((float) ($onHand[$p->pack_of_product_id] ?? 0) / max(1, (int) $p->pack_size)),
                    default => (float) ($onHand[$p->id] ?? 0),
                };

                return [
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unlimited' => (bool) $p->is_service,
                    'is_pack' => $p->pack_of_product_id !== null,
                    'is_bottle_stock' => $p->sku === $bottleSku,   // company bottles in the shop
                    'today_qty' => (float) ($s->today_qty ?? 0),
                    'today_amount' => round((float) ($s->today_amount ?? 0), 2),
                    'month_qty' => (float) ($s->month_qty ?? 0),
                    'month_amount' => round((float) ($s->month_amount ?? 0), 2),
                    'stock' => $stock,
                ];
            })->values()->all();
    }

    /**
     * Customers who currently owe money (udhaar). Highest balance first, with an
     * "over credit limit" flag so the cashier knows who to chase.
     *
     * @return array<string,mixed>
     */
    public function receivables(): array
    {
        if (! Schema::hasTable('customers')) {
            return ['rows' => [], 'count' => 0, 'total' => 0.0];
        }

        // Oldest unpaid invoice date per customer → overdue days.
        $oldest = DB::table('invoices')
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MIN(invoice_date) as oldest_date')
            ->pluck('oldest_date', 'customer_id');

        $today = now();

        $rows = \App\Models\Customer::where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->limit(50)
            ->get(['id', 'name', 'phone', 'current_balance', 'credit_limit'])
            ->map(function ($c) use ($oldest, $today) {
                $since = $oldest[$c->id] ?? null;
                $days = $since ? (int) $today->copy()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($since)->startOfDay()) : 0;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'balance' => (float) $c->current_balance,
                    'display' => Money::format($c->current_balance),
                    'over_limit' => (float) $c->credit_limit > 0 && (float) $c->current_balance > (float) $c->credit_limit,
                    'overdue_days' => $days,
                    'overdue' => $days >= 7,   // 7+ days = overdue (chase)
                ];
            });

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'total' => round((float) $rows->sum('balance'), 2),
            'total_display' => Money::format((float) $rows->sum('balance')),
        ];
    }

    private function lowStockCount(): int
    {
        if (! Schema::hasTable('stock_items')) {
            return 0;
        }

        return (int) DB::table('products')
            ->where('products.track_inventory', true)
            ->where('products.is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM stock_items WHERE stock_items.product_id = products.id) <= products.reorder_level')
            ->count();
    }

    /** @return array<string, mixed> */
    private function money(float $amount, string $label): array
    {
        return [
            'label' => $label,
            'value' => $amount,
            'display' => Money::format($amount),
            'short' => Money::short($amount),
        ];
    }

    /** @return array<string, mixed> */
    private function stat(int $count, string $unit, string $label): array
    {
        return [
            'label' => $label,
            'value' => $count,
            'unit' => $unit,
            'display' => number_format($count).' '.$unit,
        ];
    }
}

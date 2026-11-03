<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Operational profit & loss, built from source records (not the journal):
 *
 *   Sales revenue      invoice lines net of line + bill discounts, excl. tax and bottle deposits
 * − Cost of goods      quantity × unit_cost captured at the time of sale (historical cost)
 * = Gross profit       costed lines only — lines with no configured cost (e.g. 19L refill)
 *                      are reported as "pending revenue", never as 100% profit
 * − Expenses           expenses table, by spent_at (salaries are entered here when paid)
 * − Internal use       stock taken for owner/internal use at cost, less what was paid back
 * − Stock loss         damaged / wasted stock at cost
 * + Other income       incomes table
 * = Net profit
 *
 * Returned / cancelled invoices are excluded. Credit (udhaar) sales count on the invoice date.
 */
class ProfitService
{
    /** Line revenue net of line discount and its share of the bill discount (excl. tax). */
    private const REVENUE = '(invoice_items.quantity * invoice_items.unit_price - invoice_items.discount)'
        .' * (1 - COALESCE(invoices.discount / NULLIF(invoices.subtotal, 0), 0))';

    /** @return array<string, mixed> */
    public function summary(string $from, string $to): array
    {
        $sales = $this->salesLines($from, $to)->selectRaw($this->salesColumns())->first();
        $expenses = $this->expensesByCategory($from, $to);
        $internal = (float) $this->internalUse($from, $to)->sum(DB::raw('total_cost - amount_paid'));
        $loss = (float) $this->stockLoss($from, $to)->sum(DB::raw('ABS(quantity) * unit_cost'));
        $income = (float) DB::table('incomes')->whereBetween('received_at', [$from, $to])->sum('amount');

        return $this->totals(
            (float) $sales->revenue, (float) $sales->costed_revenue, (float) $sales->cogs, (float) $sales->pending_revenue,
            (float) $expenses->sum('amount'), $internal, $loss, $income,
        ) + [
            'from' => $from,
            'to' => $to,
            'invoices' => (int) $sales->invoices,
            'expenses_by_category' => $expenses->values(),
        ];
    }

    /**
     * One row per day / month in the range.
     *
     * @param 'day'|'month' $period
     * @return list<array<string, mixed>>
     */
    public function breakdown(string $from, string $to, string $period = 'day'): array
    {
        $fmt = $period === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $sales = $this->salesLines($from, $to)
            ->selectRaw("DATE_FORMAT(invoices.invoice_date, '{$fmt}') as k, ".$this->salesColumns())
            ->groupBy('k')->get()->keyBy('k');
        $expenses = DB::table('expenses')->whereBetween('spent_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(spent_at, '{$fmt}') as k, SUM(amount) as v")->groupBy('k')->pluck('v', 'k');
        $internal = $this->internalUse($from, $to)
            ->selectRaw("DATE_FORMAT(consumed_at, '{$fmt}') as k, SUM(total_cost - amount_paid) as v")->groupBy('k')->pluck('v', 'k');
        $loss = $this->stockLoss($from, $to)
            ->selectRaw("DATE_FORMAT(moved_at, '{$fmt}') as k, SUM(ABS(quantity) * unit_cost) as v")->groupBy('k')->pluck('v', 'k');
        $income = DB::table('incomes')->whereBetween('received_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(received_at, '{$fmt}') as k, SUM(amount) as v")->groupBy('k')->pluck('v', 'k');

        $keys = collect([$sales->keys(), $expenses->keys(), $internal->keys(), $loss->keys(), $income->keys()])
            ->flatten()->unique()->sortDesc()->values();

        return $keys->map(function (string $k) use ($sales, $expenses, $internal, $loss, $income) {
            $s = $sales[$k] ?? null;

            return ['period' => $k, 'invoices' => (int) ($s->invoices ?? 0)] + $this->totals(
                (float) ($s->revenue ?? 0), (float) ($s->costed_revenue ?? 0), (float) ($s->cogs ?? 0), (float) ($s->pending_revenue ?? 0),
                (float) ($expenses[$k] ?? 0), (float) ($internal[$k] ?? 0), (float) ($loss[$k] ?? 0), (float) ($income[$k] ?? 0),
            );
        })->all();
    }

    /**
     * Product-wise gross profit per channel (shop = walk-in/retail price, market = shop customers).
     *
     * @return array<string, mixed>
     */
    public function byProduct(string $from, string $to): array
    {
        $channel = "CASE WHEN invoices.type = 'wholesale' THEN 'market' ELSE 'shop' END";

        $rows = $this->salesLines($from, $to)
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->groupBy('products.id', 'products.name', 'products.sku', DB::raw($channel))
            ->orderBy('products.name')
            ->selectRaw("products.name, products.sku, {$channel} as channel, SUM(invoice_items.quantity) as qty,
                SUM(CASE WHEN invoice_items.unit_cost IS NULL THEN invoice_items.quantity ELSE 0 END) as pending_qty, "
                .$this->salesColumns())
            ->get()
            ->map(function ($r) {
                $costedQty = (float) $r->qty - (float) $r->pending_qty;
                $profit = $costedQty > 0 ? round((float) $r->costed_revenue - (float) $r->cogs, 2) : null;

                return [
                    'name' => $r->name,
                    'sku' => $r->sku,
                    'channel' => $r->channel,
                    'qty' => (float) $r->qty,
                    'revenue' => round((float) $r->revenue, 2),
                    'cogs' => round((float) $r->cogs, 2),
                    'gross_profit' => $profit,                       // null = no costed lines yet
                    'profit_per_unit' => $profit !== null ? round($profit / $costedQty, 2) : null,
                    'pending_qty' => (float) $r->pending_qty,
                    'pending_revenue' => round((float) $r->pending_revenue, 2),
                ];
            });

        return [
            'rows' => $rows->values(),
            'revenue' => round($rows->sum('revenue'), 2),
            'cogs' => round($rows->sum('cogs'), 2),
            'gross_profit' => round($rows->sum(fn ($r) => $r['gross_profit'] ?? 0), 2), // costed lines only
            'pending_revenue' => round($rows->sum('pending_revenue'), 2),
        ];
    }

    /**
     * Profit per bottle size: a single and its packs are one row (1.5L = singles + 6-packs),
     * with quantity counted in bottles. 19L new bottle, refill and cooler are their own rows.
     *
     * @return list<array<string, mixed>>
     */
    public function byBottle(string $from, string $to): array
    {
        $label = 'CASE WHEN base.sale_price > 0 THEN base.name ELSE products.name END';

        return $this->salesLines($from, $to)
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->leftJoin('products as base', 'base.id', '=', 'products.pack_of_product_id')
            ->groupBy(DB::raw('COALESCE(products.pack_of_product_id, products.id)'))
            ->orderByDesc('revenue')
            ->selectRaw("MAX({$label}) as label,
                SUM(invoice_items.quantity * CASE WHEN products.pack_of_product_id IS NULL THEN 1 ELSE products.pack_size END) as bottles,
                SUM(CASE WHEN products.pack_of_product_id IS NULL THEN invoice_items.quantity ELSE 0 END) as singles,
                SUM(CASE WHEN products.pack_of_product_id IS NULL THEN 0 ELSE invoice_items.quantity END) as packs,
                MAX(products.is_service) as is_service, "
                .$this->salesColumns())
            ->get()
            ->map(fn ($r) => [
                'label' => $r->label,
                'bottles' => (float) $r->bottles,
                'singles' => (float) $r->singles,
                'packs' => (float) $r->packs,
                'is_service' => (bool) $r->is_service,
                'revenue' => round((float) $r->revenue, 2),
                'cogs' => round((float) $r->cogs, 2),
                // null = nothing in this row has a configured cost yet (refill / cooler)
                'gross_profit' => (float) $r->costed_revenue > 0 || (float) $r->cogs > 0
                    ? round((float) $r->costed_revenue - (float) $r->cogs, 2) : null,
                'pending_revenue' => round((float) $r->pending_revenue, 2),
            ])->values()->all();
    }

    /** @return array<string, float|null> */
    private function totals(float $revenue, float $costedRevenue, float $cogs, float $pending, float $expenses, float $internal, float $loss, float $income): array
    {
        $gross = $costedRevenue - $cogs;
        $net = $gross - $expenses - $internal - $loss + $income;

        return [
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($gross, 2),
            'pending_revenue' => round($pending, 2),     // sold, but cost not configured → not in profit
            'expenses' => round($expenses, 2),
            'internal_use' => round($internal, 2),
            'stock_loss' => round($loss, 2),
            'other_income' => round($income, 2),
            'net_profit' => round($net, 2),
            'margin' => $costedRevenue > 0 ? round($net / $costedRevenue * 100, 1) : null,
        ];
    }

    private function salesColumns(): string
    {
        $rev = self::REVENUE;

        return "COUNT(DISTINCT invoices.id) as invoices,
            COALESCE(SUM({$rev}), 0) as revenue,
            COALESCE(SUM(CASE WHEN invoice_items.unit_cost IS NOT NULL THEN {$rev} ELSE 0 END), 0) as costed_revenue,
            COALESCE(SUM(CASE WHEN invoice_items.unit_cost IS NOT NULL THEN invoice_items.quantity * invoice_items.unit_cost ELSE 0 END), 0) as cogs,
            COALESCE(SUM(CASE WHEN invoice_items.unit_cost IS NULL THEN {$rev} ELSE 0 END), 0) as pending_revenue";
    }

    private function salesLines(string $from, string $to): Builder
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->whereNotIn('invoices.status', ['cancelled', 'returned'])
            ->whereNull('invoices.deleted_at');
    }

    private function expensesByCategory(string $from, string $to): Collection
    {
        return DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->whereBetween('expenses.spent_at', [$from, $to])
            ->groupBy('expense_categories.name')
            ->orderByDesc(DB::raw('SUM(expenses.amount)'))
            ->selectRaw('expense_categories.name as category, SUM(expenses.amount) as amount, COUNT(*) as count')
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'amount' => round((float) $r->amount, 2), 'count' => (int) $r->count]);
    }

    private function internalUse(string $from, string $to): Builder
    {
        return DB::table('internal_consumptions')->whereBetween('consumed_at', [$from, $to]);
    }

    private function stockLoss(string $from, string $to): Builder
    {
        return DB::table('stock_movements')
            ->whereIn('type', ['damage', 'wastage'])
            ->whereBetween('moved_at', [$from.' 00:00:00', $to.' 23:59:59']);
    }
}

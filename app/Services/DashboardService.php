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
            ->whereNotIn('status', ['cancelled', 'returned'])->sum('grand_total');

        $soldMonth = (float) Invoice::whereBetween('invoice_date', [$monthStart, $today])
            ->whereNotIn('status', ['cancelled', 'returned'])->sum('grand_total');

        $waterToday = (float) ProductionBatch::whereDate('production_date', $today)->sum('filtered_water_litres');

        $receivables = (float) Invoice::whereNotIn('status', ['cancelled', 'returned'])->sum('balance');

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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the dashboard KPIs. Domain modules (sales, production, inventory,
 * accounting) are wired in as they are built; until then the service returns
 * zeroed/placeholder figures so the dashboard renders end-to-end.
 */
class DashboardService extends BaseService
{
    /** @return array<string, mixed> */
    public function metrics(): array
    {
        return [
            'todays_production' => $this->stat(0, 'bottles', 'Today\'s Production'),
            'todays_sales' => $this->money(0, 'Today\'s Sales'),
            'monthly_revenue' => $this->money(0, 'Monthly Revenue'),
            'outstanding_receivables' => $this->money(0, 'Outstanding Receivables'),
            'profit_summary' => $this->money(0, 'Profit (This Month)'),
            'low_stock_alerts' => $this->stat($this->lowStockCount(), 'items', 'Low Stock Alerts'),
            'pending_deliveries' => $this->stat(0, 'orders', 'Pending Deliveries'),
        ];
    }

    /** Count of inventory-tracked products at or below their reorder level. */
    private function lowStockCount(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_items')) {
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

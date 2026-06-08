<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class OpeningStockSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::where('code', 'MAIN')->first() ?? Warehouse::first();
        if (! $warehouse) {
            return;
        }

        $inventory = app(InventoryService::class);

        // Opening stock at SINGLE-bottle level (packs draw from these). Skip service/pack products.
        $opening = [
            'SEAL-250' => 1000,
            'SEAL-500' => 1000,
            'SEAL-1000' => 800,
            'SEAL-1500' => 600,
            'SEAL-6000' => 300,
            '19L-BOTTLE' => 100,   // company bottles available to issue on deposit
            'WATER-BULK' => 5000,  // litres of filtered water on hand
        ];

        foreach ($opening as $sku => $qty) {
            $product = Product::where('sku', $sku)->first();
            if (! $product) {
                continue;
            }
            // Only add if there's no stock yet (idempotent-ish for re-seeds).
            $onHand = $inventory instanceof InventoryService
                ? \App\Models\StockItem::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->sum('quantity')
                : 0;
            if ((float) $onHand > 0) {
                continue;
            }
            $inventory->stockIn($warehouse->id, $product->id, $qty, (float) $product->cost_price, null, 'Opening stock');
        }
    }
}

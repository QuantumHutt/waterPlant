<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

/**
 * Keeps a product's cost current (latest purchase price / latest production cost).
 * Packs built from that bottle follow: pack cost = bottle cost × pack size.
 * Past sales keep the cost they were sold at (invoice_items.unit_cost).
 */
class ProductCostService
{
    public function set(int $productId, float $cost): void
    {
        if ($cost <= 0) {
            return;
        }
        Product::whereKey($productId)->first()?->update(['cost_price' => round($cost, 4)]);
        Product::where('pack_of_product_id', $productId)->get()
            ->each(fn (Product $pack) => $pack->update(['cost_price' => round($cost * max(1, (int) $pack->pack_size), 2)]));
    }
}

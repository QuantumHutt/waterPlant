<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface StockItemRepositoryInterface extends RepositoryInterface
{
    public function onHand(int $productId, ?int $variantId = null, ?int $warehouseId = null): float;
}

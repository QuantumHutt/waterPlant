<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Product;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findBySkuOrBarcode(string $code): ?Product;

    /** Products at or below their reorder level (needs stock-on-hand join). */
    public function lowStock(): \Illuminate\Support\Collection;
}

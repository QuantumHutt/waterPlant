<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function findBySkuOrBarcode(string $code): ?Product
    {
        /** @var Product|null $product */
        $product = $this->query()
            ->where('sku', $code)
            ->orWhere('barcode', $code)
            ->first();

        return $product;
    }

    public function lowStock(): Collection
    {
        // Sum on-hand quantity across warehouses, compare to reorder_level.
        return $this->query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->select('products.*')
            ->selectSub(
                DB::table('stock_items')
                    ->whereColumn('stock_items.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(quantity),0)'),
                'on_hand',
            )
            ->havingRaw('on_hand <= reorder_level')
            ->get();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
    }

    /** @return list<string> */
    protected function searchableColumns(): array
    {
        return ['name', 'sku', 'barcode'];
    }
}

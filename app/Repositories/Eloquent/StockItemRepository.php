<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\StockItem;
use App\Repositories\Contracts\StockItemRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class StockItemRepository extends BaseRepository implements StockItemRepositoryInterface
{
    public function __construct(StockItem $model)
    {
        parent::__construct($model);
    }

    public function onHand(int $productId, ?int $variantId = null, ?int $warehouseId = null): float
    {
        return (float) $this->query()
            ->where('product_id', $productId)
            ->when($variantId !== null, fn ($q) => $q->where('variant_id', $variantId))
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->query()->with($relations);
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if (! empty($filters['search'])) {
            $term = (string) $filters['search'];
            $query->whereHas('product', fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%"));
        }
        if (! empty($filters['low_stock'])) {
            $query->whereHas('product', fn (Builder $q) => $q->whereColumn('products.reorder_level', '>=', 'stock_items.quantity'));
        }
    }
}

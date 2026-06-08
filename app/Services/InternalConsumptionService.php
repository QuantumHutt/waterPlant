<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InternalConsumption;
use App\Models\Product;
use App\Models\StockItem;
use App\Support\Enums\StockMovementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Owner / internal use of stock. This is NOT a sale:
 *  - stock is reduced,
 *  - NO revenue and NO profit is generated,
 *  - the cost value is recorded in the Internal Consumption ledger,
 *  - the owner may optionally pay at cost (amount_paid) or take it free.
 *
 * It never creates an invoice, so it can never affect sales / profit reports.
 */
class InternalConsumptionService extends BaseService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return InternalConsumption::with(['product', 'consumer'])
            ->latest('consumed_at')->latest('id')->paginate($perPage);
    }

    /**
     * @param array{warehouse_id:int, product_id:int, variant_id?:int|null, quantity:float, pay_at_cost?:bool, amount_paid?:float, reason?:string} $data
     */
    public function record(array $data): InternalConsumption
    {
        return $this->transaction(function () use ($data): InternalConsumption {
            $selected = Product::findOrFail($data['product_id']);

            // A pack expands to its base single bottle (inventory is tracked at single level).
            $isPack = $selected->pack_of_product_id !== null;
            $stockProduct = $isPack ? Product::findOrFail($selected->pack_of_product_id) : $selected;
            $variantId = $isPack ? null : ($data['variant_id'] ?? null);
            $qty = (float) $data['quantity'] * ($isPack ? max(1, (int) $selected->pack_size) : 1);

            $product = $stockProduct;

            // Cost basis = current average cost (fallback to product cost price).
            $avg = (float) (StockItem::query()
                ->where('warehouse_id', $data['warehouse_id'])
                ->where('product_id', $product->id)
                ->where('variant_id', $variantId)
                ->value('avg_cost') ?? 0);
            $unitCost = $avg > 0 ? $avg : (float) $product->cost_price;
            $totalCost = round($unitCost * $qty, 2);

            // Owner may pay at cost, a custom amount, or nothing.
            $paid = isset($data['amount_paid'])
                ? (float) $data['amount_paid']
                : (! empty($data['pay_at_cost']) ? $totalCost : 0.0);

            // Reduce stock (records a 'consumption' movement — never a sale).
            $consumption = InternalConsumption::create([
                'reference' => 'IC-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'warehouse_id' => $data['warehouse_id'],
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'amount_paid' => $paid,
                'is_paid' => $paid >= $totalCost && $totalCost > 0,
                'reason' => $data['reason'] ?? 'Internal / owner use',
                'consumed_by' => auth()->id(),
                'consumed_at' => $data['consumed_at'] ?? now()->toDateString(),
            ]);

            $this->inventory->move([
                'warehouse_id' => $data['warehouse_id'],
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'type' => StockMovementType::Consumption,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'reference' => $consumption,
                'note' => 'Internal use: '.($data['reason'] ?? 'owner'),
            ]);

            return $consumption->load('product');
        });
    }
}

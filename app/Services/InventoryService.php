<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Support\Enums\StockMovementType;
use Illuminate\Support\Str;

/**
 * The single gateway through which stock quantities change.
 *
 * Invariant: `stock_items.quantity` is ONLY ever mutated by {@see self::move()},
 * which writes a matching `stock_movements` row inside the same transaction.
 * That keeps the on-hand figure and the immutable movement ledger in lock-step.
 */
class InventoryService extends BaseService
{
    /**
     * Apply a single stock movement and return the resulting StockItem.
     *
     * @param array{
     *   warehouse_id:int, product_id:int, variant_id?:int|null,
     *   type:StockMovementType, quantity:float, unit_cost?:float,
     *   reference?:\Illuminate\Database\Eloquent\Model|null, note?:string|null
     * } $data
     */
    public function move(array $data): StockItem
    {
        return $this->transaction(function () use ($data): StockItem {
            $type = $data['type'];
            // Adjustment carries a signed delta (+/-); all other types are directional.
            $signedQty = $type === StockMovementType::Adjustment
                ? (float) $data['quantity']
                : abs((float) $data['quantity']) * $type->sign();
            $qty = abs($signedQty);
            $unitCost = (float) ($data['unit_cost'] ?? 0);
            $variantId = $data['variant_id'] ?? null;

            /** @var StockItem $item */
            $item = StockItem::query()
                ->where('warehouse_id', $data['warehouse_id'])
                ->where('product_id', $data['product_id'])
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first()
                ?? new StockItem([
                    'warehouse_id' => $data['warehouse_id'],
                    'product_id' => $data['product_id'],
                    'variant_id' => $variantId,
                    'quantity' => 0,
                    'avg_cost' => 0,
                ]);

            $current = (float) $item->quantity;
            $newQty = $current + $signedQty;

            if ($newQty < 0) {
                $product = Product::find($data['product_id']);
                throw InsufficientStockException::for($product?->name ?? "#{$data['product_id']}", $current, $qty);
            }

            // Weighted-average cost on inbound movements that carry a cost.
            if ($type->isInbound() && $unitCost > 0 && $newQty > 0) {
                $item->avg_cost = round((($current * (float) $item->avg_cost) + ($qty * $unitCost)) / $newQty, 4);
            }

            $item->quantity = $newQty;
            $item->save();

            $reference = $data['reference'] ?? null;

            StockMovement::create([
                'warehouse_id' => $data['warehouse_id'],
                'product_id' => $data['product_id'],
                'variant_id' => $variantId,
                'type' => $type,
                'quantity' => $signedQty,
                'unit_cost' => $unitCost ?: (float) $item->avg_cost,
                'balance_after' => $newQty,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $data['note'] ?? null,
                'moved_at' => now(),
                'created_by' => auth()->id(),
            ]);

            return $item;
        });
    }

    /** Convenience: manual stock-in. */
    public function stockIn(int $warehouseId, int $productId, float $qty, float $unitCost = 0, ?int $variantId = null, ?string $note = null): StockItem
    {
        return $this->move([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'type' => StockMovementType::In,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'note' => $note,
        ]);
    }

    /** Convenience: manual stock-out. */
    public function stockOut(int $warehouseId, int $productId, float $qty, ?int $variantId = null, ?string $note = null): StockItem
    {
        return $this->move([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'type' => StockMovementType::Out,
            'quantity' => $qty,
            'note' => $note,
        ]);
    }

    /**
     * Transfer stock between two warehouses. Records a document + paired movements.
     *
     * @param array<int,array{product_id:int, variant_id?:int|null, quantity:float}> $items
     */
    public function transfer(int $fromWarehouse, int $toWarehouse, array $items, ?string $note = null): StockTransfer
    {
        if ($fromWarehouse === $toWarehouse) {
            throw new \InvalidArgumentException('Source and destination warehouses must differ.');
        }

        return $this->transaction(function () use ($fromWarehouse, $toWarehouse, $items, $note): StockTransfer {
            $transfer = StockTransfer::create([
                'reference' => $this->reference('TRF'),
                'from_warehouse_id' => $fromWarehouse,
                'to_warehouse_id' => $toWarehouse,
                'status' => 'completed',
                'note' => $note,
                'transferred_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $line) {
                $transfer->items()->create([
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'] ?? null,
                    'quantity' => $line['quantity'],
                ]);

                $this->move([
                    'warehouse_id' => $fromWarehouse,
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'] ?? null,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => $line['quantity'],
                    'reference' => $transfer,
                ]);

                $this->move([
                    'warehouse_id' => $toWarehouse,
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'] ?? null,
                    'type' => StockMovementType::TransferIn,
                    'quantity' => $line['quantity'],
                    'reference' => $transfer,
                ]);
            }

            return $transfer->load('items');
        });
    }

    /**
     * Adjust / damage / wastage / audit. Each item carries a signed `quantity`
     * delta (audit lines may instead pass counted_qty, resolved here).
     *
     * @param array<int,array{product_id:int, variant_id?:int|null, quantity?:float, counted_qty?:float, note?:string}> $items
     */
    public function adjust(int $warehouseId, string $type, array $items, ?string $reason = null): StockAdjustment
    {
        $movementType = match ($type) {
            'damage' => StockMovementType::Damage,
            'wastage' => StockMovementType::Wastage,
            default => StockMovementType::Adjustment,
        };

        return $this->transaction(function () use ($warehouseId, $type, $items, $reason, $movementType): StockAdjustment {
            $adjustment = StockAdjustment::create([
                'reference' => $this->reference('ADJ'),
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'reason' => $reason,
                'status' => 'completed',
                'adjusted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $line) {
                $variantId = $line['variant_id'] ?? null;
                $systemQty = (float) (StockItem::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $line['product_id'])
                    ->where('variant_id', $variantId)
                    ->value('quantity') ?? 0);

                // For an audit, the delta is counted - system. Otherwise use the given signed quantity.
                $delta = $type === 'audit' && isset($line['counted_qty'])
                    ? ((float) $line['counted_qty'] - $systemQty)
                    : (float) ($line['quantity'] ?? 0);

                $adjustment->items()->create([
                    'product_id' => $line['product_id'],
                    'variant_id' => $variantId,
                    'system_qty' => $systemQty,
                    'counted_qty' => $line['counted_qty'] ?? null,
                    'quantity' => $delta,
                    'note' => $line['note'] ?? null,
                ]);

                if (abs($delta) < 0.0001) {
                    continue;
                }

                // Damage/wastage are recorded under their own (reduction) movement type;
                // generic adjustments/audits use the signed Adjustment movement.
                $this->move([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $line['product_id'],
                    'variant_id' => $variantId,
                    'type' => $movementType,
                    'quantity' => $delta,
                    'reference' => $adjustment,
                    'note' => $line['note'] ?? null,
                ]);
            }

            return $adjustment->load('items');
        });
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
    }

    public function defaultWarehouse(): Warehouse
    {
        return Warehouse::default() ?? Warehouse::create([
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);
    }
}

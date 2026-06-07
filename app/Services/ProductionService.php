<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductionBatch;
use App\Models\StockItem;
use App\Support\Enums\StockMovementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductionService extends BaseService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return ProductionBatch::with(['operator', 'outputs.product', 'consumptions.product'])
            ->latest()->paginate($perPage);
    }

    /**
     * Run a batch: consume raw materials (stock out) and produce finished goods (stock in).
     * Production cost is the sum of consumed material cost; finished units get a per-unit
     * share of that cost.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): ProductionBatch
    {
        $productionDate = $data['production_date'] ?? now()->toDateString();

        // Auto-calculate expiry from configured shelf life when not supplied.
        $expiry = $data['expiry_date'] ?? null;
        if (! $expiry) {
            $shelfLife = (int) (\App\Models\Setting::get('production', 'shelf_life_days') ?? 0);
            if ($shelfLife > 0) {
                $expiry = \Illuminate\Support\Carbon::parse($productionDate)->addDays($shelfLife)->toDateString();
            }
        }

        return $this->transaction(function () use ($data, $productionDate, $expiry): ProductionBatch {
            $batch = ProductionBatch::create([
                'batch_number' => $this->generateBatchNumber(),
                'warehouse_id' => $data['warehouse_id'],
                'operator_id' => $data['operator_id'] ?? auth()->id(),
                'production_date' => $productionDate,
                'expiry_date' => $expiry,
                'status' => 'completed',
                'raw_water_litres' => $data['raw_water_litres'] ?? 0,
                'filtered_water_litres' => $data['filtered_water_litres'] ?? 0,
                'wastage_litres' => $data['wastage_litres'] ?? 0,
                'bottles_produced' => $data['bottles_produced'] ?? 0,
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Consume raw materials.
            $totalCost = 0.0;
            foreach (($data['consumptions'] ?? []) as $c) {
                $variantId = $c['variant_id'] ?? null;
                $unitCost = (float) ($c['unit_cost'] ?? StockItem::query()
                    ->where('warehouse_id', $batch->warehouse_id)
                    ->where('product_id', $c['product_id'])
                    ->where('variant_id', $variantId)
                    ->value('avg_cost') ?? 0);

                $totalCost += (float) $c['quantity'] * $unitCost;

                $batch->consumptions()->create([
                    'product_id' => $c['product_id'],
                    'variant_id' => $variantId,
                    'quantity' => $c['quantity'],
                    'unit_cost' => $unitCost,
                ]);

                $this->inventory->move([
                    'warehouse_id' => $batch->warehouse_id,
                    'product_id' => $c['product_id'],
                    'variant_id' => $variantId,
                    'type' => StockMovementType::ProductionOut,
                    'quantity' => (float) $c['quantity'],
                    'unit_cost' => $unitCost,
                    'reference' => $batch,
                    'note' => "Consumed in {$batch->batch_number}",
                ]);
            }

            // PRIMARY OUTPUT: produced filtered water (litres) increases bulk water stock.
            $litres = (float) ($data['filtered_water_litres'] ?? 0);
            if ($litres > 0) {
                $waterProduct = $this->waterProduct($batch->warehouse_id);
                if ($waterProduct) {
                    $this->inventory->move([
                        'warehouse_id' => $batch->warehouse_id,
                        'product_id' => $waterProduct->id,
                        'type' => StockMovementType::ProductionIn,
                        'quantity' => $litres,
                        'unit_cost' => round($totalCost / $litres, 4),
                        'reference' => $batch,
                        'note' => "Filtered water from {$batch->batch_number}",
                    ]);
                }
            }

            // SECONDARY: any explicitly bottled finished goods.
            $totalOutputQty = array_sum(array_map(fn ($o) => (float) $o['quantity'], $data['outputs'] ?? []));
            $perUnitCost = $totalOutputQty > 0 ? round($totalCost / max($totalOutputQty, 1), 4) : 0;

            foreach (($data['outputs'] ?? []) as $o) {
                $batch->outputs()->create([
                    'product_id' => $o['product_id'],
                    'variant_id' => $o['variant_id'] ?? null,
                    'quantity' => $o['quantity'],
                ]);

                $this->inventory->move([
                    'warehouse_id' => $batch->warehouse_id,
                    'product_id' => $o['product_id'],
                    'variant_id' => $o['variant_id'] ?? null,
                    'type' => StockMovementType::ProductionIn,
                    'quantity' => (float) $o['quantity'],
                    'unit_cost' => $perUnitCost,
                    'reference' => $batch,
                    'note' => "Produced in {$batch->batch_number}",
                ]);
            }

            $batch->update(['production_cost' => round($totalCost, 2)]);

            return $batch->load(['outputs', 'consumptions']);
        });
    }

    /** Unique, sequential batch number: BATCH-YYMMDD-#### with collision retry. */
    private function generateBatchNumber(): string
    {
        $date = now()->format('ymd');
        $seq = ProductionBatch::whereDate('created_at', now()->toDateString())->count() + 1;

        do {
            $number = 'BATCH-'.$date.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (ProductionBatch::where('batch_number', $number)->exists());

        return $number;
    }

    /** The configured bulk filtered-water product (created on demand if missing). */
    private function waterProduct(int $warehouseId): ?\App\Models\Product
    {
        $sku = \App\Models\Setting::get('production', 'water_product_sku') ?? 'WATER-BULK';

        return \App\Models\Product::where('sku', $sku)->first();
    }
}

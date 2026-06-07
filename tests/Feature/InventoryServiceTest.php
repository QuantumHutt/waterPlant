<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\Enums\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->product = Product::factory()->create();
    }

    private function onHand(): float
    {
        return (float) StockItem::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->value('quantity');
    }

    public function test_stock_in_increases_quantity_and_records_movement(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100, 10);

        $this->assertSame(100.0, $this->onHand());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => StockMovementType::In->value,
            'quantity' => 100,
            'balance_after' => 100,
        ]);
    }

    public function test_stock_out_decreases_quantity(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100, 10);
        $this->service->stockOut($this->warehouse->id, $this->product->id, 40);

        $this->assertSame(60.0, $this->onHand());
    }

    public function test_stock_out_beyond_available_throws(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 10);

        $this->expectException(InsufficientStockException::class);
        $this->service->stockOut($this->warehouse->id, $this->product->id, 50);

        // Quantity unchanged (transaction rolled back).
        $this->assertSame(10.0, $this->onHand());
    }

    public function test_weighted_average_cost(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100, 10); // avg 10
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100, 20); // avg 15

        $avg = (float) StockItem::where('product_id', $this->product->id)->value('avg_cost');
        $this->assertEqualsWithDelta(15.0, $avg, 0.0001);
    }

    public function test_transfer_moves_quantity_between_warehouses(): void
    {
        $other = Warehouse::factory()->create();
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100, 5);

        $transfer = $this->service->transfer($this->warehouse->id, $other->id, [
            ['product_id' => $this->product->id, 'quantity' => 30],
        ]);

        $this->assertSame(70.0, $this->onHand());
        $this->assertSame(30.0, (float) StockItem::where('warehouse_id', $other->id)
            ->where('product_id', $this->product->id)->value('quantity'));
        $this->assertDatabaseHas('stock_transfers', ['reference' => $transfer->reference]);
        $this->assertDatabaseCount('stock_transfer_items', 1);
    }

    public function test_positive_and_negative_adjustments(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 50);

        $this->service->adjust($this->warehouse->id, 'adjustment', [
            ['product_id' => $this->product->id, 'quantity' => 10],
        ]);
        $this->assertSame(60.0, $this->onHand());

        $this->service->adjust($this->warehouse->id, 'adjustment', [
            ['product_id' => $this->product->id, 'quantity' => -25],
        ]);
        $this->assertSame(35.0, $this->onHand());
    }

    public function test_wastage_reduces_stock(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 80);

        $this->service->adjust($this->warehouse->id, 'wastage', [
            ['product_id' => $this->product->id, 'quantity' => -12],
        ]);

        $this->assertSame(68.0, $this->onHand());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => StockMovementType::Wastage->value,
        ]);
    }

    public function test_audit_sets_quantity_to_counted_value(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100);

        // Physical count finds only 92.
        $this->service->adjust($this->warehouse->id, 'audit', [
            ['product_id' => $this->product->id, 'counted_qty' => 92],
        ]);

        $this->assertSame(92.0, $this->onHand());
    }

    public function test_on_hand_always_equals_sum_of_movements(): void
    {
        $this->service->stockIn($this->warehouse->id, $this->product->id, 100, 10);
        $this->service->stockOut($this->warehouse->id, $this->product->id, 30);
        $this->service->adjust($this->warehouse->id, 'adjustment', [
            ['product_id' => $this->product->id, 'quantity' => 5],
        ]);

        $sumMovements = (float) StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->sum('quantity');

        $this->assertSame($this->onHand(), $sumMovements);
        $this->assertSame(75.0, $sumMovements);
    }
}

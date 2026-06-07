<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductionService;
use App\Services\SaleService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterProductionRefillTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Product $water;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(WarehouseSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->warehouse = Warehouse::where('code', 'MAIN')->first();
        $this->water = Product::where('sku', 'WATER-BULK')->firstOrFail();

        $user = User::factory()->create();
        $user->assignRole('production-manager');
        $this->actingAs($user);
    }

    private function waterOnHand(): float
    {
        return (float) StockItem::where('product_id', $this->water->id)
            ->where('warehouse_id', $this->warehouse->id)->sum('quantity');
    }

    public function test_batch_increases_filtered_water_stock(): void
    {
        app(ProductionService::class)->create([
            'warehouse_id' => $this->warehouse->id,
            'filtered_water_litres' => 1000,
            'bottles_produced' => 0,
        ]);

        $this->assertSame(1000.0, $this->waterOnHand());
        $this->assertDatabaseHas('production_batches', ['filtered_water_litres' => 1000]);
    }

    public function test_batch_number_is_unique_and_auto_generated(): void
    {
        $service = app(ProductionService::class);
        $a = $service->create(['warehouse_id' => $this->warehouse->id, 'filtered_water_litres' => 100]);
        $b = $service->create(['warehouse_id' => $this->warehouse->id, 'filtered_water_litres' => 100]);

        $this->assertNotSame($a->batch_number, $b->batch_number);
        $this->assertStringStartsWith('BATCH-', $a->batch_number);
    }

    public function test_expiry_auto_calculates_from_shelf_life(): void
    {
        $batch = app(ProductionService::class)->create([
            'warehouse_id' => $this->warehouse->id,
            'filtered_water_litres' => 500,
            'production_date' => '2026-01-01',
        ]);

        // SettingSeeder configures 180 days shelf life.
        $this->assertSame('2026-06-30', $batch->expiry_date->toDateString());
    }

    public function test_batch_consumption_reduces_raw_stock(): void
    {
        $cap = Product::where('sku', 'CAP-STD')->firstOrFail();
        app(InventoryService::class)->stockIn($this->warehouse->id, $cap->id, 1000, 0.8);

        app(ProductionService::class)->create([
            'warehouse_id' => $this->warehouse->id,
            'filtered_water_litres' => 500,
            'consumptions' => [['product_id' => $cap->id, 'quantity' => 200]],
        ]);

        $capOnHand = (float) StockItem::where('product_id', $cap->id)->sum('quantity');
        $this->assertSame(800.0, $capOnHand);
    }

    public function test_refill_service_deducts_only_water_no_bottle(): void
    {
        app(ProductionService::class)->create(['warehouse_id' => $this->warehouse->id, 'filtered_water_litres' => 1000]);

        $refill = Product::where('sku', '19L-REFILL')->firstOrFail();   // service, water_litres=19
        $companyBottle = Product::where('sku', '19L-BOTTLE')->firstOrFail();
        app(InventoryService::class)->stockIn($this->warehouse->id, $companyBottle->id, 30, 700);
        $bottleBefore = (float) StockItem::where('product_id', $companyBottle->id)->sum('quantity');

        $invoice = app(SaleService::class)->create([
            'warehouse_id' => $this->warehouse->id,
            'type' => 'refill',
            'items' => [['product_id' => $refill->id, 'quantity' => 1, 'unit_price' => 200]],
        ]);

        $this->assertSame('refill', $invoice->type);
        $this->assertSame(981.0, $this->waterOnHand());                 // 1000 - 19
        $this->assertSame($bottleBefore, (float) StockItem::where('product_id', $companyBottle->id)->sum('quantity'));
        // The service product itself holds no stock row.
        $this->assertSame(0, StockItem::where('product_id', $refill->id)->count());
    }

    public function test_new_19l_bottle_sale_deducts_bottle_and_water_and_records_deposit(): void
    {
        app(ProductionService::class)->create(['warehouse_id' => $this->warehouse->id, 'filtered_water_litres' => 1000]);
        $refill = Product::where('sku', '19L-REFILL')->firstOrFail();
        $companyBottle = Product::where('sku', '19L-BOTTLE')->firstOrFail();
        app(InventoryService::class)->stockIn($this->warehouse->id, $companyBottle->id, 30, 700);

        $customer = \App\Models\Customer::create(['code' => 'C1', 'name' => 'Ali', 'type' => 'retail']);

        $invoice = app(SaleService::class)->create([
            'warehouse_id' => $this->warehouse->id,
            'type' => 'new_bottle',
            'customer_id' => $customer->id,
            'new_bottle' => true,
            'deposit' => 1000,
            'items' => [['product_id' => $refill->id, 'quantity' => 1, 'unit_price' => 200]],
        ]);

        $this->assertSame(981.0, $this->waterOnHand());                                 // water -19
        $this->assertSame(29.0, (float) StockItem::where('product_id', $companyBottle->id)->sum('quantity')); // bottle -1
        $this->assertSame(1000.0, (float) $invoice->fresh()->deposit_total);
        $this->assertDatabaseHas('customer_gallon_balances', ['customer_id' => $customer->id, 'held_qty' => 1, 'deposit_held' => 1000]);
    }

    public function test_sealed_bottle_sale_reduces_finished_goods_only(): void
    {
        $sealed = Product::where('sku', 'SEAL-500')->firstOrFail();
        app(InventoryService::class)->stockIn($this->warehouse->id, $sealed->id, 100, 8);
        app(ProductionService::class)->create(['warehouse_id' => $this->warehouse->id, 'filtered_water_litres' => 500]);
        $waterBefore = $this->waterOnHand();

        app(SaleService::class)->create([
            'warehouse_id' => $this->warehouse->id,
            'type' => 'pos',
            'items' => [['product_id' => $sealed->id, 'quantity' => 10, 'unit_price' => 30]],
        ]);

        $this->assertSame(90.0, (float) StockItem::where('product_id', $sealed->id)->sum('quantity'));
        $this->assertSame($waterBefore, $this->waterOnHand());  // sealed sale does NOT touch bulk water
    }
}

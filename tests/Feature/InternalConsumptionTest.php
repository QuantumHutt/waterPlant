<?php
namespace Tests\Feature;
use App\Models\{User,Product,StockItem,Invoice,InternalConsumption};
use App\Support\Enums\RoleName;
use App\Services\{InventoryService,InternalConsumptionService,DashboardService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class InternalConsumptionTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  public function test_free_consumption_minus_stock_no_sale(){
    $p=Product::where('sku','SEAL-500')->first(); // cost 8
    $before=(float)StockItem::where('product_id',$p->id)->sum('quantity');
    $c=app(InternalConsumptionService::class)->record(['warehouse_id'=>$this->wh(),'product_id'=>$p->id,'quantity'=>10,'reason'=>'ghar']);
    $this->assertSame($before-10,(float)StockItem::where('product_id',$p->id)->sum('quantity')); // stock down
    $this->assertSame(0,Invoice::count());                 // NO sale
    $this->assertEqualsWithDelta(130.0,(float)$c->total_cost,0.01); // 10 * 13 (avg cost) recorded
    $this->assertSame(0.0,(float)$c->amount_paid);
    $this->assertFalse((bool)$c->is_paid);
    // Dashboard sales unaffected.
    $this->assertSame(0.0, app(DashboardService::class)->metrics()['todays_sales']['value']);
  }
  public function test_paid_at_cost(){
    $p=Product::where('sku','SEAL-500')->first();
    $c=app(InternalConsumptionService::class)->record(['warehouse_id'=>$this->wh(),'product_id'=>$p->id,'quantity'=>5,'pay_at_cost'=>true]);
    $this->assertEqualsWithDelta(65.0,(float)$c->total_cost,0.01); // 5 * 13
    $this->assertEqualsWithDelta(65.0,(float)$c->amount_paid,0.01);
    $this->assertTrue((bool)$c->is_paid);
    $this->assertSame(0,Invoice::count()); // still not a sale
  }
}

<?php
namespace Tests\Feature;
use App\Models\{User,Product,StockItem,Customer,CustomerGallonBalance,GallonTransaction,Invoice};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BottleExchangeTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Salesman->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  private function bottles(){ return (float)StockItem::where('product_id',Product::where('sku','19L-BOTTLE')->value('id'))->sum('quantity'); }
  private function refill($qty,$returned,$customer=null){
    return $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'refill','customer_id'=>$customer,'bottles_returned'=>$returned,
      'items'=>[['product_id'=>Product::where('sku','19L-REFILL')->value('id'),'quantity'=>$qty,'unit_price'=>100]]]);
  }
  private function customer(){ return Customer::create(['name'=>'Test Shop','code'=>'C-T1','type'=>'wholesale','is_active'=>true]); }

  public function test_5_delivered_4_returned_leaves_1_outstanding(){
    $c=$this->customer();
    $this->refill(5,4,$c->id)->assertSessionHasNoErrors();
    $this->assertSame(1,CustomerGallonBalance::where('customer_id',$c->id)->value('held_qty'));
    $this->assertSame(99.0,$this->bottles());                     // 1 company bottle left the shop
    $inv=Invoice::latest('id')->first();
    $this->assertSame([5,4],[$inv->bottles_delivered,$inv->bottles_returned]);
    $this->assertEquals(500,(float)$inv->grand_total);
  }

  public function test_returning_old_bottles_reduces_balance_and_restocks(){
    $c=$this->customer();
    $this->refill(5,4,$c->id);
    $this->refill(2,3,$c->id)->assertSessionHasNoErrors();        // brings back the one owed
    $this->assertSame(0,CustomerGallonBalance::where('customer_id',$c->id)->value('held_qty'));
    $this->assertSame(100.0,$this->bottles());
  }

  public function test_walk_in_plain_swap_needs_no_customer(){
    $this->refill(2,2)->assertSessionHasNoErrors();
    $this->assertSame(100.0,$this->bottles());
    $this->assertSame(0,GallonTransaction::count());
  }

  public function test_walk_in_short_return_requires_customer(){
    $this->refill(3,2)->assertSessionHasErrors('customer_id');
    $this->assertSame(0,Invoice::count());                         // whole sale rolled back
    $this->assertSame(100.0,$this->bottles());
  }

  public function test_sale_without_bottle_field_is_untracked(){
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos',
      'items'=>[['product_id'=>Product::where('sku','SEAL-6000')->value('id'),'quantity'=>1,'unit_price'=>100]]])->assertSessionHasNoErrors();
    $this->assertNull(Invoice::latest('id')->first()->bottles_delivered);
  }

  public function test_refills_are_unlimited_and_counted(){
    $water=Product::where('sku','WATER-BULK')->value('id');
    \App\Models\StockItem::where('product_id',$water)->update(['quantity'=>0]);   // no bulk water at all
    $this->refill(30,30)->assertSessionHasNoErrors();                               // still sells
    $this->assertSame(0.0,(float)StockItem::where('product_id',$water)->sum('quantity'));
    $this->get(route('sales.index'))->assertInertia(fn($pg)=>$pg->where('todayCounts.'.Product::where('sku','19L-REFILL')->value('id'),30));
  }

  public function test_dashboard_stock_and_sales(){
    $this->refill(3,3);
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos',
      'items'=>[['product_id'=>Product::where('sku','PACK-1500-6')->value('id'),'quantity'=>2,'unit_price'=>300]]]);
    $rows=collect(app(\App\Services\DashboardService::class)->stockAndSales())->keyBy('sku');
    $this->assertTrue($rows['19L-REFILL']['unlimited']);
    $this->assertEquals(3,$rows['19L-REFILL']['today_qty']);
    $this->assertNull($rows['19L-REFILL']['stock']);
    $this->assertEquals(2,$rows['PACK-1500-6']['month_qty']);
    $this->assertEquals(98,$rows['PACK-1500-6']['stock']);        // (600 − 12) / 6
    $this->assertEquals(588,$rows['SEAL-1500']['stock']);
    $this->assertEquals(100,$rows['19L-BOTTLE']['stock']);
  }

  public function test_pet_packs_have_market_price(){
    $this->assertEquals(300,(float)Product::where('sku','PACK-1500-6')->value('wholesale_price'));
    $this->assertEquals(420,(float)Product::where('sku','PACK-500-12')->value('wholesale_price'));
  }
}

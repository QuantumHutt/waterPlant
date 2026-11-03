<?php
namespace Tests\Feature;
use App\Models\{User,Product,StockItem,InvoiceItem};
use App\Support\Enums\RoleName;
use App\Services\SaleService;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ConfirmedPricingTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  private function p($sku){ return Product::where('sku',$sku)->firstOrFail(); }
  private function sell($sku,$qty,$price,$type='pos',$extra=[]){
    return app(SaleService::class)->create(array_merge(['warehouse_id'=>$this->wh(),'type'=>$type,'items'=>[['product_id'=>$this->p($sku)->id,'quantity'=>$qty,'unit_price'=>$price]]],$extra));
  }
  private function gp(){
    $rows=null;
    $this->get(route('reports.index'))->assertOk()->assertInertia(function($pg) use (&$rows){ $rows=$pg->toArray()['props']['grossProfit']; return $pg; });
    return $rows;
  }
  private function row($gp,$sku,$channel){ return collect($gp['rows'])->first(fn($r)=>$r['sku']===$sku && $r['channel']===$channel); }

  public function test_confirmed_product_configuration(){
    $this->assertEquals([55,100,70],[(float)$this->p('SEAL-6000')->cost_price,(float)$this->p('SEAL-6000')->sale_price,(float)$this->p('SEAL-6000')->wholesale_price]);
    $this->assertEquals([37,222,300],[(float)$this->p('SEAL-1500')->cost_price,(float)$this->p('PACK-1500-6')->cost_price,(float)$this->p('PACK-1500-6')->sale_price]);
    $this->assertEquals([27,324,420],[(float)$this->p('SEAL-500')->cost_price,(float)$this->p('PACK-500-12')->cost_price,(float)$this->p('PACK-500-12')->sale_price]);
    $this->assertEquals([100,50],[(float)$this->p('SEAL-1500')->sale_price,(float)$this->p('SEAL-500')->sale_price]); // single shop prices
    $cooler=$this->p('COOLER-REFILL');
    $this->assertTrue($cooler->is_manual_price); $this->assertTrue($cooler->is_service); $this->assertEquals(0,(float)$cooler->cost_price);
    $this->assertEquals(0,(float)$this->p('19L-REFILL')->cost_price);  // refill: whole amount is profit
    $this->assertEquals(100,(float)$this->p('19L-REFILL')->sale_price);
    $new=$this->p('19L-NEW');
    $this->assertEquals([1250,1300],[(float)$new->cost_price,(float)$new->sale_price]);
    $this->assertSame($this->p('19L-BOTTLE')->id,$new->pack_of_product_id);
    $this->assertEquals(1250,(float)$this->p('19L-BOTTLE')->cost_price);
  }

  public function test_pricing_seeder_is_idempotent_and_leaves_history_alone(){
    $inv=$this->sell('SEAL-6000',1,100);
    $this->p('SEAL-6000')->update(['cost_price'=>60]);   // later cost change…
    $this->seed(ProductSeeder::class);
    $this->seed(ProductSeeder::class);
    $this->assertSame(1,Product::where('sku','19L-NEW')->count());
    $this->assertEquals(55,(float)$this->p('SEAL-6000')->cost_price);
    $this->assertEquals(55,(float)$inv->items->first()->unit_cost); // historical cost kept
  }

  public function test_catalog_only_lists_products_the_shop_sells(){
    foreach(ProductSeeder::RETIRED as $sku) $this->assertFalse((bool)Product::where('sku',$sku)->value('is_active') ,$sku);
    $active=Product::where('is_active',true)->pluck('sku')->sort()->values()->all();
    $this->assertEquals(['19L-BOTTLE','19L-NEW','19L-REFILL','BOTTLE-CHARGE','BTL-1500','BTL-500','BTL-6000','CAP-STD','COOLER-REFILL','PACK-1500-6','PACK-500-12','SEAL-1500','SEAL-500','SEAL-6000','WATER-BULK'],$active);
  }

  public function test_6l_shop_and_market_gross_profit(){
    $this->sell('SEAL-6000',2,100,'pos');        // own shop
    $this->sell('SEAL-6000',3,70,'wholesale');   // market
    $gp=$this->gp();
    $this->assertEquals(90,$this->row($gp,'SEAL-6000','shop')['gross_profit']);    // 2 × 45
    $this->assertEquals(45,$this->row($gp,'SEAL-6000','shop')['profit_per_unit']);
    $this->assertEquals(45,$this->row($gp,'SEAL-6000','market')['gross_profit']);  // 3 × 15
    $this->assertEquals(15,$this->row($gp,'SEAL-6000','market')['profit_per_unit']);
  }

  public function test_pack_sales_deduct_bottles_and_profit(){
    $this->sell('PACK-1500-6',1,300);
    $this->sell('PACK-500-12',2,420);
    $this->assertSame(594.0,(float)StockItem::where('product_id',$this->p('SEAL-1500')->id)->sum('quantity')); // 600-6
    $this->assertSame(976.0,(float)StockItem::where('product_id',$this->p('SEAL-500')->id)->sum('quantity'));  // 1000-24
    $gp=$this->gp();
    $this->assertEquals(78,$this->row($gp,'PACK-1500-6','shop')['profit_per_unit']);
    $this->assertEquals(192,$this->row($gp,'PACK-500-12','shop')['gross_profit']); // 2 × 96
  }

  public function test_new_bottle_is_separate_with_50_profit_and_deducts_company_bottle(){
    $this->sell('19L-NEW',1,1300);
    $this->assertSame(99.0,(float)StockItem::where('product_id',$this->p('19L-BOTTLE')->id)->sum('quantity'));
    $gp=$this->gp();
    $this->assertEquals(50,$this->row($gp,'19L-NEW','shop')['gross_profit']);   // 1300 − 1250
    $this->assertNull($this->row($gp,'19L-REFILL','shop'));   // not mixed with refills
  }

  public function test_refill_profit_is_pending_not_100_percent(){
    // Any product with NO cost configured (NULL) stays out of profit — here the refill, with its cost cleared.
    $this->p('19L-REFILL')->update(['cost_price'=>null]);
    $this->sell('19L-REFILL',5,100,'refill');
    $this->sell('SEAL-6000',1,100);
    $this->assertNull(InvoiceItem::where('product_id',$this->p('19L-REFILL')->id)->first()->unit_cost);
    $this->artisan('refills:zero-cost')->assertSuccessful();             // dry run changes nothing
    $this->assertNull(InvoiceItem::where('product_id',$this->p('19L-REFILL')->id)->first()->unit_cost);
    $gp=$this->gp();
    $r=$this->row($gp,'19L-REFILL','shop');
    $this->assertNull($r['gross_profit']);
    $this->assertEquals(5,$r['pending_qty']);
    $this->assertEquals(500,$gp['pending_revenue']);
    $this->assertEquals(45,$gp['gross_profit']);              // only the costed 6L line
    $this->artisan('refills:zero-cost --apply')->assertSuccessful();   // owner's rule: refill = full profit
    $this->assertEquals(545,$this->gp()['gross_profit']);     // 45 + 5 × 100
  }

  public function test_manual_price_and_discounts_reduce_profit(){
    // Owner gives 6L cheaper: 2 × 90 with Rs 10 line discount, plus Rs 17 bill discount.
    $inv=app(SaleService::class)->create(['warehouse_id'=>$this->wh(),'type'=>'pos','discount'=>17,
      'items'=>[['product_id'=>$this->p('SEAL-6000')->id,'quantity'=>2,'unit_price'=>90,'discount'=>10],
                ['product_id'=>$this->p('PACK-1500-6')->id,'quantity'=>1,'unit_price'=>300]]]);
    $this->assertEquals(453,(float)$inv->grand_total);       // 170 + 300 − 17
    $gp=$this->gp();
    $this->assertEquals(453,$gp['revenue']);
    $this->assertEquals(453-110-222,$gp['gross_profit']);    // revenue − (2×55 + 222)
  }

  public function test_pack_return_restocks_base_bottles(){
    $inv=$this->sell('PACK-1500-6',2,300);
    app(SaleService::class)->returnInvoice($inv->fresh(),'test');
    $this->assertSame(600.0,(float)StockItem::where('product_id',$this->p('SEAL-1500')->id)->sum('quantity'));
  }
}

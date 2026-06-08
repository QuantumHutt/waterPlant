<?php
namespace Tests\Feature;
use App\Models\{User,Product,StockItem,Invoice};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PosSaleTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::SalesManager->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  public function test_pos_single_sale(){
    $p=Product::where('sku','SEAL-250')->first();
    $r=$this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$p->id,'quantity'=>3,'unit_price'=>20]]]);
    $r->assertSessionHasNoErrors(); $r->assertRedirect();
    $this->assertSame(997.0,(float)StockItem::where('product_id',$p->id)->sum('quantity'));
    $this->assertSame(1,Invoice::where('type','pos')->count());
  }
  public function test_pos_pack_sale(){
    $pack=Product::where('sku','PACK-500-24')->first(); $base=Product::where('sku','SEAL-500')->first();
    $r=$this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$pack->id,'quantity'=>1,'unit_price'=>684]]]);
    $r->assertSessionHasNoErrors();
    $this->assertSame(976.0,(float)StockItem::where('product_id',$base->id)->sum('quantity')); // 1000-24
  }
  public function test_oversell_shows_error_flash(){
    $p=Product::where('sku','SEAL-6000')->first();
    $r=$this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$p->id,'quantity'=>9999,'unit_price'=>150]]]);
    $r->assertSessionHas('error');
  }

  public function test_paid_pos_sale_is_not_double_counted(){
    $p=Product::where('sku','SEAL-250')->first();
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$p->id,'quantity'=>12,'unit_price'=>20]]]);
    $inv=Invoice::latest('id')->first();
    $this->assertSame(240.0,(float)$inv->grand_total);
    $this->assertSame(240.0,(float)$inv->paid_total);   // paid once, not doubled
    $this->assertSame(0.0,(float)$inv->balance);        // not -240
    $this->assertSame('paid',$inv->status);
  }
}

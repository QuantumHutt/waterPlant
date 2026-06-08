<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,StockItem,Invoice,InternalConsumption};
use App\Support\Enums\RoleName;
use App\Services\{SaleService,InternalConsumptionService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosComprehensiveTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  private function stock($sku){ $p=Product::where('sku',$sku)->first(); return (float)StockItem::where('product_id',$p->id)->sum('quantity'); }
  private function pid($sku){ return Product::where('sku',$sku)->first()->id; }

  public function test_multi_item_cash_sale(){
    $b250=$this->stock('SEAL-250'); $b500=$this->stock('SEAL-500');
    $r=$this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','paid_total'=>110,
      'items'=>[['product_id'=>$this->pid('SEAL-250'),'quantity'=>3,'unit_price'=>20],['product_id'=>$this->pid('SEAL-500'),'quantity'=>1,'unit_price'=>30]]]);
    $r->assertSessionHasNoErrors();
    $inv=Invoice::latest('id')->first();
    $this->assertEqualsWithDelta(90.0,(float)$inv->grand_total,0.01);
    $this->assertEqualsWithDelta(0.0,(float)$inv->balance,0.01);
    $this->assertSame($b250-3,$this->stock('SEAL-250'));
    $this->assertSame($b500-1,$this->stock('SEAL-500'));
  }

  public function test_discount(){
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','discount'=>20,'paid_total'=>80,
      'items'=>[['product_id'=>$this->pid('SEAL-500'),'quantity'=>2,'unit_price'=>50]]])->assertSessionHasNoErrors();
    $inv=Invoice::latest('id')->first();
    $this->assertEqualsWithDelta(80.0,(float)$inv->grand_total,0.01); // 100-20
    $this->assertEqualsWithDelta(0.0,(float)$inv->balance,0.01);
  }

  public function test_overpayment_caps_paid_no_negative_balance(){
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','paid_total'=>500,
      'items'=>[['product_id'=>$this->pid('SEAL-250'),'quantity'=>1,'unit_price'=>20]]])->assertSessionHasNoErrors();
    $inv=Invoice::latest('id')->first();
    $this->assertEqualsWithDelta(20.0,(float)$inv->paid_total,0.01); // capped at grand
    $this->assertEqualsWithDelta(0.0,(float)$inv->balance,0.01);     // never negative
  }

  public function test_credit_sale_full_receivable(){
    $c=Customer::create(['code'=>'CR1','name'=>'Karim','type'=>'retail','current_balance'=>0]);
    app(SaleService::class)->create(['warehouse_id'=>$this->wh(),'customer_id'=>$c->id,'type'=>'credit',
      'items'=>[['product_id'=>$this->pid('SEAL-500'),'quantity'=>2,'unit_price'=>30]]]);
    $inv=Invoice::latest('id')->first();
    $this->assertSame('unpaid',$inv->status);
    $this->assertEqualsWithDelta(60.0,(float)$inv->balance,0.01);
    $this->assertEqualsWithDelta(60.0,(float)$c->fresh()->current_balance,0.01);
  }

  public function test_oversell_rolls_back_whole_sale(){
    $before250=$this->stock('SEAL-250');
    $r=$this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos',
      'items'=>[['product_id'=>$this->pid('SEAL-250'),'quantity'=>2,'unit_price'=>20],['product_id'=>$this->pid('SEAL-6000'),'quantity'=>99999,'unit_price'=>150]]]);
    $r->assertSessionHas('error');
    $this->assertSame(0,Invoice::count());                 // no invoice
    $this->assertSame($before250,$this->stock('SEAL-250')); // first item NOT deducted (rollback)
  }

  public function test_wholesale_sale(){
    app(SaleService::class)->create(['warehouse_id'=>$this->wh(),'type'=>'wholesale','paid_total'=>1000,
      'items'=>[['product_id'=>$this->pid('SEAL-500'),'quantity'=>50,'unit_price'=>20]]]);
    $this->assertSame('wholesale',Invoice::latest('id')->first()->type);
  }

  public function test_owner_custom_low_price(){
    app(SaleService::class)->create(['warehouse_id'=>$this->wh(),'type'=>'pos',
      'items'=>[['product_id'=>$this->pid('SEAL-500'),'quantity'=>1,'unit_price'=>15]]]);
    $this->assertEqualsWithDelta(15.0,(float)Invoice::latest('id')->first()->grand_total,0.01);
  }

  public function test_internal_consumption_no_sale_no_dashboard_impact(){
    $before=$this->stock('SEAL-500');
    app(InternalConsumptionService::class)->record(['warehouse_id'=>$this->wh(),'product_id'=>$this->pid('SEAL-500'),'quantity'=>5,'reason'=>'home']);
    $this->assertSame($before-5,$this->stock('SEAL-500'));
    $this->assertSame(0,Invoice::count());
    $this->assertSame(1,InternalConsumption::count());
    $this->assertEqualsWithDelta(0.0,app(\App\Services\DashboardService::class)->metrics()['todays_sales']['value'],0.01);
  }

  public function test_internal_consumption_pack_deducts_base(){
    $base=$this->stock('SEAL-500');
    app(InternalConsumptionService::class)->record(['warehouse_id'=>$this->wh(),'product_id'=>$this->pid('PACK-500-24'),'quantity'=>1]);
    $this->assertSame($base-24,$this->stock('SEAL-500')); // 1 pack = 24 base bottles
  }
}

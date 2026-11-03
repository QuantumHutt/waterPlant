<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,CustomerGallonBalance,StockItem,Invoice};
use App\Support\Enums\RoleName;
use App\Services\{CashBookService,ProfitService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BottleFlowsTest extends TestCase {
  use RefreshDatabase;
  private Customer $c;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $this->as(RoleName::Manager);
    $this->c=Customer::create(['code'=>'C1','name'=>'Shop A','type'=>'wholesale','current_balance'=>0,'is_active'=>true]);
    // 5 refills given, 2 empties back → customer holds 3 company bottles; shop 97
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'refill','customer_id'=>$this->c->id,'bottles_returned'=>2,
      'items'=>[['product_id'=>Product::where('sku','19L-REFILL')->value('id'),'quantity'=>5,'unit_price'=>100]]]);
  }
  private function as($role){ $u=User::factory()->create(); $u->assignRole($role->value); $this->actingAs($u); return $u; }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->value('id'); }
  private function held(){ return (int)CustomerGallonBalance::where('customer_id',$this->c->id)->value('held_qty'); }
  private function shop(){ return (float)StockItem::where('product_id',Product::where('sku','19L-BOTTLE')->value('id'))->sum('quantity'); }
  private function bottle($data){ return $this->post(route('customers.bottles',$this->c),$data); }
  private function today(){ return now()->toDateString(); }

  public function test_empties_back_without_refill_salesman_can_do_it(){
    $this->as(RoleName::Salesman);
    $this->assertEquals([3,97],[$this->held(),$this->shop()]);
    $this->bottle(['action'=>'return','quantity'=>2])->assertSessionHas('success');
    $this->assertEquals([1,99],[$this->held(),$this->shop()]);
    $this->bottle(['action'=>'return','quantity'=>5])->assertSessionHasErrors('quantity');  // only 1 left
    $this->bottle(['action'=>'missing','quantity'=>1])->assertForbidden();                  // losses: managers only
    $this->get(route('sales.index'))->assertInertia(fn($pg)=>$pg->where('customers.0.bottles_held',1));
  }

  public function test_lost_bottle_written_off_is_a_loss(){
    $this->bottle(['action'=>'missing','quantity'=>1])->assertSessionHas('success');
    $this->assertEquals(2,$this->held());
    $p=app(ProfitService::class)->summary($this->today(),$this->today());
    $this->assertEquals(1250,$p['stock_loss']);                                               // bottle cost
    $this->assertEquals(97,$this->shop());                                                    // stock unchanged — bottle was already out
  }

  public function test_broken_bottle_charged_cash_or_udhaar(){
    $this->bottle(['action'=>'damaged','quantity'=>1,'charge_by'=>'cash','price'=>1300])->assertSessionHas('success');
    $inv=Invoice::latest('id')->first();
    $this->assertSame(['paid',1300.0],[$inv->status,(float)$inv->grand_total]);
    $p=app(ProfitService::class)->summary($this->today(),$this->today());
    $this->assertEquals(0,$p['stock_loss']);                                                  // charged → no loss
    $this->assertEquals(1300+500,app(CashBookService::class)->day($this->today())['cash']['closing']);   // cash = what was charged
    $this->bottle(['action'=>'missing','quantity'=>1,'charge_by'=>'udhaar','price'=>1000])->assertSessionHas('success');
    $this->assertEquals(1000,(float)$this->c->fresh()->current_balance);
    // 5 refills × 100 = 500 · 1300 charged at cost 1250 → +50 · 1000 charged at cost 1250 → −250
    $this->assertEquals(300,app(ProfitService::class)->summary($this->today(),$this->today())['gross_profit']);
    $this->assertEquals(1,$this->held());
  }

  public function test_deposit_received_is_cash_and_refunded_when_bottle_back(){
    $d=Customer::create(['code'=>'C2','name'=>'Home B','type'=>'retail','current_balance'=>0,'is_active'=>true]);
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'new_bottle','new_bottle'=>true,'deposit'=>1000,'customer_id'=>$d->id,
      'items'=>[['product_id'=>Product::where('sku','19L-REFILL')->value('id'),'quantity'=>1,'unit_price'=>100]]]);
    $lines=collect(app(CashBookService::class)->day($this->today())['cash']['lines'])->keyBy('kind');
    $this->assertEquals(1000,$lines['bottle_deposit']['amount']);                             // deposit came into the drawer
    $this->post(route('customers.bottles',$d),['action'=>'deposit_refund','quantity'=>1,'amount'=>1000,'method'=>'cash'])->assertSessionHas('success');
    $bal=CustomerGallonBalance::where('customer_id',$d->id)->first();
    $this->assertEquals([0,0.0],[$bal->held_qty,(float)$bal->deposit_held]);
    $lines=collect(app(CashBookService::class)->day($this->today())['cash']['lines'])->keyBy('kind');
    $this->assertEquals(1000,$lines['deposit_refund']['amount']);
    $this->post(route('customers.bottles',$d),['action'=>'deposit_refund','quantity'=>1,'amount'=>1000])->assertSessionHasErrors('quantity');
  }

  public function test_cancelling_deposit_bill_refunds_deposit_and_bottle(){
    $d=Customer::create(['code'=>'C3','name'=>'Home C','type'=>'retail','current_balance'=>0,'is_active'=>true]);
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'new_bottle','new_bottle'=>true,'deposit'=>1000,'customer_id'=>$d->id,
      'items'=>[['product_id'=>Product::where('sku','19L-REFILL')->value('id'),'quantity'=>1,'unit_price'=>100]]]);
    $inv=Invoice::latest('id')->first();
    $before=$this->shop();
    $this->post(route('sales.cancel',$inv),['reason'=>'wrong customer'])->assertSessionHas('success');
    $this->assertEquals($before+1,$this->shop());
    $bal=CustomerGallonBalance::where('customer_id',$d->id)->first();
    $this->assertEquals([0,0.0],[$bal->held_qty,(float)$bal->deposit_held]);
    $this->assertEquals(500,app(CashBookService::class)->day($this->today())['cash']['closing']); // only the first refill sale remains
  }
}

<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,Invoice,StockItem,Supplier,Payment};
use App\Support\Enums\RoleName;
use App\Services\{CashBookService,ProfitService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ReturnsAndPurchasesTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Manager->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->value('id'); }
  private function pid($sku){ return Product::where('sku',$sku)->value('id'); }
  private function stock($sku){ return (float)StockItem::where('product_id',$this->pid($sku))->sum('quantity'); }
  private function sell($sku,$qty,$price,$extra=[]){
    $this->post(route('sales.store'),array_merge(['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$this->pid($sku),'quantity'=>$qty,'unit_price'=>$price]]],$extra));
    return Invoice::latest('id')->first();
  }
  private function cash(){ return app(CashBookService::class)->day(now()->toDateString())['cash']['closing']; }

  public function test_partial_return_refunds_cash_and_restocks(){
    $inv=$this->sell('SEAL-6000',5,100);
    $item=$inv->items->first();
    $this->post(route('sales.return',$inv),['items'=>[$item->id=>2],'refund_method'=>'cash'])->assertSessionHas('success');
    $inv->refresh();
    $this->assertSame(['paid',200.0],[$inv->status,(float)$inv->returned_total]);
    $this->assertEquals(297,$this->stock('SEAL-6000'));                              // 300 − 5 + 2
    $this->assertEquals(300,$this->cash());                                          // 500 in, 200 refunded
    $p=app(ProfitService::class)->summary(now()->toDateString(),now()->toDateString());
    $this->assertEquals([300,135],[$p['revenue'],$p['gross_profit']]);               // 3 kept × 45
    $this->post(route('sales.return',$inv),['items'=>[$item->id=>4]])->assertSessionHasErrors('items'); // only 3 left
  }

  public function test_return_on_udhaar_bill_reduces_udhaar_first(){
    $c=Customer::create(['code'=>'C1','name'=>'Shop','type'=>'retail','current_balance'=>0,'is_active'=>true]);
    $inv=$this->sell('SEAL-6000',5,100,['customer_id'=>$c->id,'paid_total'=>200]);  // 300 udhaar
    $this->post(route('sales.return',$inv),['items'=>[$inv->items->first()->id=>4]]); // 400 back: 300 udhaar + 100 refund
    $this->assertEquals(0,(float)$c->fresh()->current_balance);
    $this->assertEquals(0,(float)$inv->fresh()->balance);
    $this->assertEquals(100,$this->cash());                                          // 200 in − 100 refund
  }

  public function test_return_kept_as_advance(){
    $c=Customer::create(['code'=>'C2','name'=>'Shop','type'=>'retail','current_balance'=>0,'is_active'=>true]);
    $inv=$this->sell('SEAL-6000',2,100,['customer_id'=>$c->id]);
    $this->post(route('sales.return',$inv),['items'=>[$inv->items->first()->id=>1],'refund_method'=>'advance']);
    $this->assertEquals(-100,(float)$c->fresh()->current_balance);                  // 100 advance for next bill
    $this->assertEquals(200,$this->cash());                                          // no cash left the drawer
  }

  public function test_pack_partial_return_restocks_bottles(){
    $inv=$this->sell('PACK-1500-6',3,300);
    $this->post(route('sales.return',$inv),['items'=>[$inv->items->first()->id=>1]]);
    $this->assertEquals(588,$this->stock('SEAL-1500'));                              // 600 − 18 + 6
  }

  public function test_cancel_bill_needs_permission_and_reverses_everything(){
    $inv=$this->sell('SEAL-6000',3,100);
    $s=User::factory()->create(); $s->assignRole(RoleName::Salesman->value);
    $this->actingAs($s)->post(route('sales.cancel',$inv),['reason'=>'x'])->assertForbidden();
    $m=User::factory()->create(); $m->assignRole(RoleName::Manager->value); $this->actingAs($m);
    $this->post(route('sales.cancel',$inv),[])->assertSessionHasErrors('reason');
    $this->post(route('sales.cancel',$inv),['reason'=>'wrong item'])->assertSessionHas('success');
    $this->assertSame('cancelled',$inv->fresh()->status);
    $this->assertEquals(300,$this->stock('SEAL-6000'));
    $this->assertEquals(0,$this->cash());
    $this->assertEquals(0,app(ProfitService::class)->summary(now()->toDateString(),now()->toDateString())['revenue']);
  }

  public function test_buy_empty_bottles_and_caps_receive_and_pay_now(){
    $sup=Supplier::create(['code'=>'S1','name'=>'Pak Plastics','current_balance'=>0,'is_active'=>true]);
    $this->sell('SEAL-6000',10,100);                                                 // 1000 cash in drawer
    $this->get(route('purchases.index'))->assertInertia(fn($pg)=>$pg->where('products',fn($p)=>collect($p)->pluck('sku')->contains('BTL-1500') && collect($p)->pluck('sku')->contains('CAP-STD') && ! collect($p)->pluck('sku')->contains('19L-REFILL')));
    $this->post(route('purchases.store'),['supplier_id'=>$sup->id,'warehouse_id'=>$this->wh(),'receive_now'=>true,'paid_amount'=>600,'paid_method'=>'cash',
      'items'=>[['product_id'=>$this->pid('BTL-1500'),'quantity'=>20,'unit_cost'=>28],['product_id'=>$this->pid('CAP-STD'),'quantity'=>100,'unit_cost'=>1]]])->assertSessionHas('success');
    $this->assertEquals([20,100],[$this->stock('BTL-1500'),$this->stock('CAP-STD')]);
    $this->assertEquals(28,(float)Product::find($this->pid('BTL-1500'))->cost_price);  // cost updated to new price
    $this->assertEquals(60,(float)$sup->fresh()->current_balance);                    // 660 − 600 paid
    $this->assertEquals(400,$this->cash());                                           // 1000 − 600
    $this->post(route('suppliers.payment',$sup),['amount'=>100])->assertSessionHasErrors('amount'); // owe only 60
    $this->post(route('suppliers.payment',$sup),['amount'=>60,'method'=>'bank'])->assertSessionHasNoErrors();
    $this->assertEquals(0,(float)$sup->fresh()->current_balance);
  }

  public function test_new_purchase_price_updates_bottle_and_pack_cost(){
    $sup=Supplier::create(['code'=>'S2','name'=>'Bottler','current_balance'=>0,'is_active'=>true]);
    $this->post(route('purchases.store'),['supplier_id'=>$sup->id,'warehouse_id'=>$this->wh(),'receive_now'=>true,
      'items'=>[['product_id'=>$this->pid('SEAL-1500'),'quantity'=>60,'unit_cost'=>40]]]);
    $this->assertEquals(40,(float)Product::find($this->pid('SEAL-1500'))->cost_price);
    $this->assertEquals(240,(float)Product::find($this->pid('PACK-1500-6'))->cost_price); // 6 × 40
    $this->assertEquals(2400,(float)$sup->fresh()->current_balance);                      // pay later
  }
}

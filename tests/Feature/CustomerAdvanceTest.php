<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,Invoice,Payment};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CustomerAdvanceTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Salesman->value); $this->actingAs($u);   // salesman flow
  }
  private function customer(){ return Customer::create(['code'=>'C1','name'=>'Shop A','type'=>'wholesale','current_balance'=>0,'is_active'=>true]); }
  private function sell($c,$qty,$extra=[]){   // 6L at market price 70
    return $this->post(route('sales.store'),array_merge(['warehouse_id'=>\App\Models\Warehouse::where('code','MAIN')->value('id'),'type'=>'wholesale','customer_id'=>$c->id,
      'items'=>[['product_id'=>Product::where('sku','SEAL-6000')->value('id'),'quantity'=>$qty,'unit_price'=>70]]],$extra));
  }
  private function cashIn(){ return (float)Payment::where('direction','in')->where('method','!=','advance')->sum('amount'); }

  public function test_advance_is_deducted_from_a_bigger_bill(){
    $c=$this->customer();
    $this->post(route('customers.advance',$c),['amount'=>300])->assertSessionHas('success');
    $this->assertEquals(-300,(float)$c->fresh()->current_balance);
    $this->sell($c,10,['paid_total'=>400])->assertSessionHas('success');          // 700 bill: 300 advance + 400 cash
    $inv=Invoice::latest('id')->first();
    $this->assertSame(['paid',0.0,700.0],[$inv->status,(float)$inv->balance,(float)$inv->paid_total]);
    $this->assertEquals(0,(float)$c->fresh()->current_balance);
    $this->assertEquals(700,$this->cashIn());                                    // 300 + 400, advance not counted twice
    $this->assertStringContainsString('advance used',session('success'));
  }

  public function test_bill_smaller_than_advance_leaves_the_rest(){
    $c=$this->customer();
    $this->post(route('customers.advance',$c),['amount'=>300]);
    $this->sell($c,2)->assertSessionHasNoErrors();                                // 140 bill, cash default → 0 needed
    $inv=Invoice::latest('id')->first();
    $this->assertSame('paid',$inv->status);
    $this->assertEquals(-160,(float)$c->fresh()->current_balance);               // 160 advance left
    $this->assertEquals(300,$this->cashIn());                                    // no new cash for this bill
    $this->sell($c,3,['paid_total'=>50])->assertSessionHasNoErrors();             // 210: 160 advance + 50 cash
    $this->assertEquals(0,(float)$c->fresh()->current_balance);
  }

  public function test_advance_only_when_no_udhaar(){
    $c=$this->customer();
    $this->sell($c,2,['paid_total'=>0]);                                           // 140 udhaar
    $this->post(route('customers.advance',$c),['amount'=>100])->assertSessionHasErrors('amount');
    $this->assertEquals(140,(float)$c->fresh()->current_balance);
  }

  public function test_advance_part_then_udhaar_for_the_rest(){
    $c=$this->customer();
    $this->post(route('customers.advance',$c),['amount'=>100]);
    $this->sell($c,5,['paid_total'=>0]);                                           // 350: 100 advance, 250 udhaar
    $inv=Invoice::latest('id')->first();
    $this->assertSame(['partial',250.0],[$inv->status,(float)$inv->balance]);
    $this->assertEquals(250,(float)$c->fresh()->current_balance);
  }

  public function test_profit_counts_the_sale_not_the_advance(){
    $c=$this->customer();
    $this->post(route('customers.advance',$c),['amount'=>1000]);
    $this->sell($c,2);
    $p=app(\App\Services\ProfitService::class)->summary(now()->toDateString(),now()->toDateString());
    $this->assertEquals(140,$p['revenue']);                                      // advance is not a sale
    $this->assertEquals(30,$p['gross_profit']);                                  // 2 × 15
  }
}

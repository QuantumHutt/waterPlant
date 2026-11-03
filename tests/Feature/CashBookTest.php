<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,Invoice,ExpenseCategory,CashClosing};
use App\Support\Enums\RoleName;
use App\Services\CashBookService;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,ExpenseSetupSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CashBookTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class,ExpenseSetupSeeder::class] as $s) $this->seed($s);
  }
  private function as($role){ $u=User::factory()->create(); $u->assignRole($role->value); $this->actingAs($u); return $u; }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->value('id'); }
  private function sell($qty,$extra=[]){   // 6L at 100
    return $this->post(route('sales.store'),array_merge(['warehouse_id'=>$this->wh(),'type'=>'pos',
      'items'=>[['product_id'=>Product::where('sku','SEAL-6000')->value('id'),'quantity'=>$qty,'unit_price'=>100]]],$extra));
  }
  private function day(){ return app(CashBookService::class)->day(now()->toDateString()); }

  public function test_1000_sale_800_to_bank_200_stays_in_drawer(){
    $this->as(RoleName::Admin);
    $this->sell(10)->assertSessionHasNoErrors();                                      // 1000 cash
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>800])->assertSessionHas('success');
    $d=$this->day();
    $this->assertEquals(200,$d['cash']['closing']);
    $this->assertEquals(800,$d['bank']['closing']);
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>500])->assertSessionHasErrors('amount'); // only 200 left
  }

  public function test_daily_deposits_add_up_over_the_month(){
    $this->as(RoleName::Admin);
    $this->travelTo(now()->startOfMonth()->addDays(1));
    $this->sell(55);                                                                   // 5500
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>5000,'note'=>'slip 1']);
    $this->travel(1)->days();
    $this->sell(30);                                                                   // 3000
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>2500,'note'=>'slip 2']);
    $this->travel(1)->days();
    $this->post(route('cash.transfer'),['direction'=>'withdraw','amount'=>700]);       // took 700 out for wages
    $m=app(CashBookService::class)->period(now()->startOfMonth()->toDateString(),now()->toDateString());
    $this->assertEquals([7500,700],[$m['deposited_total'],$m['withdrawn_total']]);
    $this->assertEquals([5000,7500],[$m['deposits'][0]['running'],$m['deposits'][1]['running']]);
    $this->assertEquals(6800,$m['bank']['closing']);                                   // 7500 − 700
    $this->assertEquals(1700,$m['cash']['closing']);                                   // 8500 − 7500 + 700
    $this->get(route('cash.index'))->assertOk()->assertInertia(fn($pg)=>$pg->where('month.deposited_total',7500));
    $this->get(route('reports.index',['from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString()]))->assertOk()
      ->assertInertia(fn($pg)=>$pg->where('cashPeriod.deposited_total',7500)->where('cashPeriod.bank.closing',6800));
  }

  public function test_hisaab_flow_bank_big_amount_keep_float(){
    $this->as(RoleName::Admin);
    \App\Models\Setting::set('cash','drawer_float',500,'int');
    $this->travelTo(now()->startOfMonth()->addDays(1)); $d1=now()->toDateString();
    $this->sell(55);                                                                   // 5500 cash
    $this->post(route('expenses.store'),['category_id'=>ExpenseCategory::where('name','Electricity')->value('id'),'amount'=>200,'paid_via'=>'cash']);
    $h=app(CashBookService::class)->hisaab();
    $this->assertNull($h['since']);
    $this->assertEquals([5300,500,4800],[$h['drawer_now'],$h['float'],$h['suggested_deposit']]);
    $this->get(route('dashboard'))->assertInertia(fn($pg)=>$pg->where('cashToday.suggested',4800)->where('cashToday.float',500));
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>4800,'note'=>'slip A'])->assertSessionHasNoErrors();   // first hisaab

    $this->travel(2)->days(); $d2=now()->toDateString();                               // two days later, next hisaab
    $this->sell(20);                                                                   // 2000
    $c=Customer::create(['code'=>'C1','name'=>'Shop','type'=>'wholesale','current_balance'=>0,'is_active'=>true]);
    $this->sell(3,['customer_id'=>$c->id,'paid_total'=>0]);
    $this->post(route('customers.payment',$c),['amount'=>300,'method'=>'cash']);       // udhaar 300
    $h=app(CashBookService::class)->hisaab();
    $this->assertEquals($d1,$h['since']);
    $this->assertEquals(500,$h['opening']);                                            // the float left last time
    $this->assertEquals([2800,2300],[$h['drawer_now'],$h['suggested_deposit']]);
    $lines=collect($h['lines'])->keyBy('kind');
    $this->assertEquals([2000,300],[$lines['sale']['amount'],$lines['udhaar']['amount']]);
    $this->post(route('cash.float'),['float'=>800])->assertSessionHasNoErrors();        // keep more change from now
    $this->assertEquals(2000,app(CashBookService::class)->hisaab()['suggested_deposit']);
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>2000,'note'=>'slip B']);

    $hist=app(CashBookService::class)->hisaabHistory();
    $this->assertCount(2,$hist);
    $this->assertEquals([$d1,$d2,2000,300,0,2800,2000,800],[$hist[0]['from'],$hist[0]['to'],$hist[0]['sales'],$hist[0]['udhaar'],$hist[0]['expenses'],$hist[0]['drawer_before'],$hist[0]['deposited'],$hist[0]['kept']]);
    $this->assertEquals([null,$d1,5500,200,5300,4800,500],[$hist[1]['from'],$hist[1]['to'],$hist[1]['sales'],$hist[1]['expenses'],$hist[1]['drawer_before'],$hist[1]['deposited'],$hist[1]['kept']]);
    $this->get(route('cash.index'))->assertOk()->assertInertia(fn($pg)=>$pg->where('hisaab.drawer_now',800)->has('history',2));
    $this->get(route('cash.hisaab',['to'=>$d2]))->assertOk()->assertSee('Hisaab');
    $this->assertEquals(6800,app(CashBookService::class)->day($d2)['bank']['closing']);
  }

  public function test_drawer_counts_sales_udhaar_advance_expenses_and_supplier(){
    $this->as(RoleName::Admin);
    $c=Customer::create(['code'=>'C1','name'=>'Shop','type'=>'wholesale','current_balance'=>0,'is_active'=>true]);
    $this->sell(5);                                                                    // +500 cash sale
    $this->sell(3,['customer_id'=>$c->id,'paid_total'=>0]);                            // udhaar 300, no cash
    $this->travel(1)->days();
    $this->post(route('customers.payment',$c),['amount'=>300,'method'=>'cash']);       // +300 udhaar received
    $this->post(route('expenses.store'),['category_id'=>ExpenseCategory::where('name','Electricity')->value('id'),'amount'=>120,'paid_via'=>'cash']); // −120
    $this->post(route('expenses.store'),['category_id'=>ExpenseCategory::where('name','Rent')->value('id'),'amount'=>1000,'paid_via'=>'bank']);       // bank −1000
    $d=$this->day();
    $this->assertEquals(500,$d['cash']['opening']);
    $lines=collect($d['cash']['lines'])->keyBy('kind');
    $this->assertEquals(300,$lines['udhaar']['amount']);
    $this->assertEquals(120,$lines['expense']['amount']);
    $this->assertEquals(680,$d['cash']['closing']);
    $this->assertEquals(-1000,$d['bank']['closing']);
  }

  public function test_close_day_records_short(){
    $this->as(RoleName::Salesman);
    $this->sell(2);                                                                    // 200 expected
    $this->post(route('cash.close'),['date'=>now()->toDateString(),'counted_cash'=>190])->assertSessionHas('success');
    $c=CashClosing::first();
    $this->assertEquals([200,190,-10],[(float)$c->expected_cash,(float)$c->counted_cash,(float)$c->difference]);
    $this->assertStringContainsString('SHORT',session('success'));
  }

  public function test_salesman_expense_from_pos_and_no_bank_deposit(){
    $this->as(RoleName::Salesman);
    $this->sell(3);
    $this->get(route('sales.index'))->assertInertia(fn($pg)=>$pg->has('expenseCategories'));
    $this->post(route('expenses.store'),['category_id'=>ExpenseCategory::where('name','Refreshments (Tea / Food)')->value('id'),'amount'=>50,'paid_via'=>'cash','note'=>'chai'])->assertSessionHasNoErrors();
    $this->assertEquals(250,$this->day()['cash']['closing']);
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>100])->assertForbidden();
    $this->get(route('cash.index'))->assertOk();
  }

  public function test_dashboard_shows_expenses_and_net_profit(){
    $this->as(RoleName::Admin);
    $this->sell(10);                                                                   // gross 450
    $this->post(route('expenses.store'),['category_id'=>ExpenseCategory::where('name','Rent')->value('id'),'amount'=>200,'paid_via'=>'cash']);
    $this->get(route('dashboard'))->assertInertia(fn($pg)=>$pg->where('profit.today.expenses',200)->where('profit.today.net_profit',250)->where('profit.month.gross_profit',450));
  }
}

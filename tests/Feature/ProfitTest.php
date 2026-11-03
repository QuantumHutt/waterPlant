<?php
namespace Tests\Feature;
use App\Models\{User,Product,ExpenseCategory};
use App\Support\Enums\RoleName;
use App\Services\{SaleService,ExpenseService,InventoryService,InternalConsumptionService,ProfitService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,ExpenseSetupSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProfitTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class,ExpenseSetupSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  private function pid($sku){ return Product::where('sku',$sku)->value('id'); }
  private function sell($sku,$qty,$price,$extra=[]){
    return app(SaleService::class)->create(array_merge(['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$this->pid($sku),'quantity'=>$qty,'unit_price'=>$price]]],$extra));
  }
  private function expense($cat,$amount,$date=null){
    app(ExpenseService::class)->create(['category_id'=>ExpenseCategory::where('name',$cat)->value('id'),'amount'=>$amount,'paid_via'=>'cash','spent_at'=>$date??now()->toDateString()]);
  }
  private function today(){ return app(ProfitService::class)->summary(now()->toDateString(),now()->toDateString()); }

  public function test_net_profit_is_gross_profit_minus_expenses(){
    $this->sell('SEAL-6000',10,100);                  // gross 10 × 45 = 450
    $this->sell('PACK-1500-6',2,300);                 // gross 2 × 78 = 156
    $this->expense('Electricity',200);
    $this->expense('Rent',100);
    $p=$this->today();
    $this->assertEquals(1600,$p['revenue']);
    $this->assertEquals(994,$p['cogs']);              // 550 + 444
    $this->assertEquals(606,$p['gross_profit']);
    $this->assertEquals(300,$p['expenses']);
    $this->assertEquals(306,$p['net_profit']);
    $this->assertEquals(['Electricity','Rent'],collect($p['expenses_by_category'])->pluck('category')->all());
  }

  public function test_returned_sale_and_deposit_are_not_profit(){
    $inv=$this->sell('SEAL-6000',2,100);
    app(SaleService::class)->returnInvoice($inv->fresh(),'x');
    $customer=\App\Models\Customer::create(['name'=>'C','code'=>'C1','type'=>'retail','is_active'=>true]);
    $this->sell('19L-NEW',1,1300,['customer_id'=>$customer->id]);   // profit 50
    $p=$this->today();
    $this->assertEquals(1300,$p['revenue']);
    $this->assertEquals(50,$p['net_profit']);
  }

  public function test_bill_discount_reduces_profit(){
    $this->sell('SEAL-6000',2,100,['discount'=>20]);  // 200 − 20 − 110
    $this->assertEquals(70,$this->today()['net_profit']);
  }

  public function test_refill_is_full_profit(){
    $this->sell('19L-REFILL',4,100,['type'=>'refill']);
    $this->expense('Electricity',50);
    $p=$this->today();
    $this->assertEquals([400,0,400,350],[$p['revenue'],$p['pending_revenue'],$p['gross_profit'],$p['net_profit']]);
  }

  public function test_product_without_cost_is_pending_not_profit(){
    Product::find($this->pid('19L-REFILL'))->update(['cost_price'=>null]);
    $this->sell('19L-REFILL',4,100,['type'=>'refill']);
    $this->expense('Electricity',50);
    $p=$this->today();
    $this->assertEquals(400,$p['revenue']);
    $this->assertEquals(400,$p['pending_revenue']);
    $this->assertEquals(0,$p['gross_profit']);
    $this->assertEquals(-50,$p['net_profit']);       // expenses still count
    $this->assertNull($p['margin']);
  }

  public function test_internal_use_damage_and_other_income(){
    $this->sell('SEAL-6000',10,100);                  // gross 450
    app(InternalConsumptionService::class)->record(['warehouse_id'=>$this->wh(),'product_id'=>$this->pid('SEAL-6000'),'quantity'=>2]); // free → −110
    app(InventoryService::class)->adjust($this->wh(),'damage',[['product_id'=>$this->pid('SEAL-500'),'quantity'=>-3]],'broken');      // −81
    \App\Models\Income::create(['reference'=>'INC-1','account_id'=>\App\Models\Account::byCode('4900')->id,'amount'=>40,'received_at'=>now()->toDateString()]);
    $p=$this->today();
    $this->assertEquals(110,$p['internal_use']);
    $this->assertEquals(81,$p['stock_loss']);
    $this->assertEquals(40,$p['other_income']);
    $this->assertEquals(450-110-81+40,$p['net_profit']);
  }

  public function test_historical_cost_is_kept_after_cost_change(){
    $this->sell('SEAL-6000',1,100);
    Product::find($this->pid('SEAL-6000'))->update(['cost_price'=>90]);
    $this->assertEquals(45,$this->today()['gross_profit']);
  }

  public function test_daily_and_monthly_breakdown(){
    $this->sell('SEAL-6000',1,100);
    $this->expense('Rent',30,now()->subDay()->toDateString());
    $from=now()->subDays(2)->toDateString(); $to=now()->toDateString();
    $daily=collect(app(ProfitService::class)->breakdown($from,$to,'day'))->keyBy('period');
    $this->assertEquals(45,$daily[now()->toDateString()]['net_profit']);
    $this->assertEquals(-30,$daily[now()->subDay()->toDateString()]['net_profit']);
    $monthly=app(ProfitService::class)->breakdown(now()->startOfMonth()->toDateString(),$to,'month');
    $expected=now()->subDay()->isSameMonth(now()) ? 15 : 45;
    $this->assertEquals($expected,collect($monthly)->firstWhere('period',now()->format('Y-m'))['net_profit']);
  }

  public function test_dashboard_shows_profit_only_to_report_viewers(){
    $this->sell('SEAL-6000',1,100);
    $this->get(route('dashboard'))->assertInertia(fn($pg)=>$pg->where('profit.today.net_profit',45)->has('profit.daily'));
    $d=User::factory()->create(); $d->assignRole(RoleName::DeliveryStaff->value); $this->actingAs($d);
    $this->get(route('dashboard'))->assertInertia(fn($pg)=>$pg->where('profit',null));
  }

  public function test_profit_by_bottle_combines_singles_and_packs(){
    $this->sell('SEAL-1500',4,100);                    // 4 × (100 − 37) = 252
    $this->sell('PACK-1500-6',2,300);                  // 2 × 78 = 156 → 12 bottles
    $this->sell('SEAL-6000',3,100);                    // 3 × 45 = 135
    $this->sell('19L-NEW',1,1300);                     // 0
    $this->sell('19L-REFILL',5,100,['type'=>'refill']); // full profit
    $rows=collect(app(ProfitService::class)->byBottle(now()->toDateString(),now()->toDateString()))->keyBy('label');
    $this->assertEquals([16,4,2,408],[$rows['1.5 Liter Bottle']['bottles'],$rows['1.5 Liter Bottle']['singles'],$rows['1.5 Liter Bottle']['packs'],$rows['1.5 Liter Bottle']['gross_profit']]);
    $this->assertEquals(135,$rows['6 Liter Bottle']['gross_profit']);
    $this->assertEquals(50,$rows['19L New Bottle']['gross_profit']);
    $this->assertEquals(500,$rows['19 Liter Refill Service']['gross_profit']);
    $this->assertEquals(5,$rows['19 Liter Refill Service']['bottles']);
    $this->get(route('dashboard'))->assertInertia(fn($pg)=>$pg->has('profit.bottles_today',4)->has('profit.bottles_month'));
  }

  public function test_reports_page_has_pnl_daily_monthly(){
    $this->sell('SEAL-6000',1,100);
    $this->get(route('reports.index'))->assertInertia(fn($pg)=>$pg->where('pnl.net_profit',45)->has('daily')->has('monthly'));
  }
}

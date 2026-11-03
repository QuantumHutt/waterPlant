<?php
namespace Tests\Feature;
/**
 * One full shop day through the REAL pages (POS, expenses, purchase, supplier, cash book),
 * then every report must agree: Dashboard, Reports, Cash Book, stock.
 */
use App\Models\{User,Product,Customer,Supplier,Setting,StockItem,ExpenseCategory};
use App\Support\Enums\RoleName;
use App\Services\ProductionService;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,ExpenseSetupSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class FullDayScenarioTest extends TestCase {
  use RefreshDatabase;
  private array $out = [];
  private function as($role){ $u=User::factory()->create(); $u->assignRole($role->value); $this->actingAs($u); return $u; }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->value('id'); }
  private function pid($sku){ return Product::where('sku',$sku)->value('id'); }
  private function stock($sku){ return (float)StockItem::where('product_id',$this->pid($sku))->sum('quantity'); }
  private function props($route,$params=[]){ $p=null; $this->get(route($route,$params))->assertOk()->assertInertia(function($pg) use (&$p){ $p=$pg->toArray()['props']; return $pg; }); return $p; }
  private function cat($n){ return ExpenseCategory::where('name',$n)->value('id'); }

  public function test_full_day_every_report_agrees(){
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class,ExpenseSetupSeeder::class] as $s) $this->seed($s);
    $today=now()->toDateString(); $wh=$this->wh();
    Setting::set('cash','opening_cash',5000,'int'); Setting::set('cash','opening_bank',5000,'int');

    // ---- Morning: owner buys empties + caps, fills 60 × 1.5L ----
    $this->as(RoleName::Admin);
    $sup=Supplier::create(['code'=>'S1','name'=>'Pak Plastics','current_balance'=>0,'is_active'=>true]);
    $this->post(route('purchases.store'),['supplier_id'=>$sup->id,'warehouse_id'=>$wh,'receive_now'=>true,'paid_amount'=>1500,'paid_method'=>'cash',
      'items'=>[['product_id'=>$this->pid('BTL-1500'),'quantity'=>100,'unit_cost'=>28],['product_id'=>$this->pid('CAP-STD'),'quantity'=>100,'unit_cost'=>1]]])->assertSessionHasNoErrors();
    app(ProductionService::class)->create(['warehouse_id'=>$wh,'consumptions'=>[
        ['product_id'=>$this->pid('BTL-1500'),'quantity'=>60],['product_id'=>$this->pid('CAP-STD'),'quantity'=>60],['product_id'=>$this->pid('WATER-BULK'),'quantity'=>90]],
      'outputs'=>[['product_id'=>$this->pid('SEAL-1500'),'quantity'=>60]]]);
    $this->assertEquals(31.25,(float)Product::find($this->pid('SEAL-1500'))->cost_price);

    // ---- Salesman on the POS ----
    $this->as(RoleName::Salesman);
    $shop=Customer::create(['code'=>'C1','name'=>'Shop A','type'=>'wholesale','current_balance'=>0,'is_active'=>true]);
    $sale=fn($items,$extra=[])=>$this->post(route('sales.store'),array_merge(['warehouse_id'=>$wh,'type'=>'pos','items'=>$items],$extra))->assertSessionHasNoErrors();
    $sale([['product_id'=>$this->pid('SEAL-6000'),'quantity'=>2,'unit_price'=>100]]);                                    // walk-in 200 cash
    $sale([['product_id'=>$this->pid('SEAL-6000'),'quantity'=>5,'unit_price'=>70]],['type'=>'wholesale','customer_id'=>$shop->id,'paid_total'=>0]); // 350 udhaar
    $sale([['product_id'=>$this->pid('PACK-1500-6'),'quantity'=>1,'unit_price'=>300]]);                                  // 300 cash
    $sale([['product_id'=>$this->pid('19L-REFILL'),'quantity'=>3,'unit_price'=>100]],['type'=>'refill','bottles_returned'=>3]); // 300 cash
    $this->post(route('customers.payment',$shop),['amount'=>200,'method'=>'cash'])->assertSessionHasNoErrors();         // udhaar collected
    $this->post(route('expenses.store'),['category_id'=>$this->cat('Electricity'),'amount'=>120,'paid_via'=>'cash'])->assertSessionHasNoErrors();

    // ---- Owner: rent from bank, pays supplier, deposits cash, closes ----
    $this->as(RoleName::Admin);
    $this->post(route('expenses.store'),['category_id'=>$this->cat('Rent'),'amount'=>1000,'paid_via'=>'bank'])->assertSessionHasNoErrors();
    $this->post(route('suppliers.payment',$sup),['amount'=>400,'method'=>'bank'])->assertSessionHasNoErrors();
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>800])->assertSessionHasNoErrors();
    $this->post(route('cash.close'),['date'=>$today,'counted_cash'=>3580])->assertSessionHasNoErrors();

    // ================= CHECK EVERY REPORT =================
    // Expected: revenue 1150 · cost 110+275+187.5+0 = 572.5 · gross 577.5 · expenses 1120 · net −542.5
    $d=$this->props('dashboard'); $t=$d['profit']['today'];
    $this->assertEquals([1150,572.5,577.5,1120,-542.5,0],[$t['revenue'],$t['cogs'],$t['gross_profit'],$t['expenses'],$t['net_profit'],$t['pending_revenue']]);
    $this->assertEquals(1150,$d['metrics']['todays_sales']['value']);
    $this->assertEquals(150,$d['metrics']['outstanding_receivables']['value']);       // 350 − 200
    $ss=collect($d['stockSales'])->keyBy('sku');
    $this->assertEquals([7,293],[$ss['SEAL-6000']['today_qty'],$ss['SEAL-6000']['stock']]);
    $this->assertEquals([1,109],[$ss['PACK-1500-6']['today_qty'],$ss['PACK-1500-6']['stock']]);  // (600+60−6)/6
    $this->assertEquals(3,$ss['19L-REFILL']['today_qty']);
    $bt=collect($d['profit']['bottles_today'])->keyBy('label');
    $this->assertEquals([7,165],[$bt['6 Liter Bottle']['bottles'],$bt['6 Liter Bottle']['gross_profit']]);          // 2×45 + 5×15
    $this->assertEquals([2,90,5,75],[$bt['6 Liter Bottle']['shop_bottles'],$bt['6 Liter Bottle']['shop_profit'],$bt['6 Liter Bottle']['market_bottles'],$bt['6 Liter Bottle']['market_profit']]);
    $this->assertEquals([6,112.5],[$bt['1.5 Liter Bottle']['bottles'],$bt['1.5 Liter Bottle']['gross_profit']]);   // 300 − 187.5
    $this->assertEquals([3,300],[$bt['19 Liter Refill Service']['bottles'],$bt['19 Liter Refill Service']['gross_profit']]);

    $r=$this->props('reports.index',['from'=>$today,'to'=>$today]);
    $this->assertEquals([1150,1000,150],[$r['sales']['total'],$r['sales']['received'],$r['sales']['outstanding']]);
    $this->assertEquals(-542.5,$r['pnl']['net_profit']);
    $this->assertEquals(['Rent','Electricity'],collect($r['pnl']['expenses_by_category'])->pluck('category')->all());
    $this->assertEquals([1,2900,1900,1000],[$r['purchases']['count'],$r['purchases']['total'],$r['purchases']['paid'],$r['purchases']['supplier_outstanding']]);
    $this->assertEquals([200,150],[$r['udhaar_collected'],$r['customer_outstanding']]);
    $this->assertEquals(-542.5,collect($r['daily'])->firstWhere('period',$today)['net_profit']);
    $gp=collect($r['grossProfit']['rows']);
    $this->assertEquals(15,$gp->first(fn($x)=>$x['sku']==='SEAL-6000'&&$x['channel']==='market')['profit_per_unit']);
    $this->assertEquals(45,$gp->first(fn($x)=>$x['sku']==='SEAL-6000'&&$x['channel']==='shop')['profit_per_unit']);

    // Cash: 5000 + 800 sales + 200 udhaar − 120 − 1500 supplier − 800 deposit = 3580 · Bank: 5000 + 800 − 1000 − 400 = 4400
    $c=$this->props('cash.index',['date'=>$today])['day'];
    $this->assertEquals([5000,3580],[$c['cash']['opening'],$c['cash']['closing']]);
    $this->assertEquals([5000,4400],[$c['bank']['opening'],$c['bank']['closing']]);
    $cl=collect($c['cash']['lines'])->keyBy('kind');
    $this->assertEquals([800,200,120,1500,800],[$cl['sale']['amount'],$cl['udhaar']['amount'],$cl['expense']['amount'],$cl['supplier']['amount'],$cl['deposit']['amount']]);
    $this->assertEquals([3580,3580,0],[(float)$c['closing']['expected_cash'],(float)$c['closing']['counted_cash'],(float)$c['closing']['difference']]);

    // Stock & balances
    $this->assertEquals([293,654,40,40,100],[$this->stock('SEAL-6000'),$this->stock('SEAL-1500'),$this->stock('BTL-1500'),$this->stock('CAP-STD'),$this->stock('19L-BOTTLE')]);
    $this->assertEquals(1000,(float)$sup->fresh()->current_balance);
    $this->assertEquals(150,(float)$shop->fresh()->current_balance);

    file_put_contents(getenv('SCENARIO_OUT') ?: '/dev/null', json_encode(['today'=>$t,'cash'=>$c['cash'],'bank'=>$c['bank'],'purchases'=>$r['purchases'],'bottles'=>$d['profit']['bottles_today'],'sales'=>$r['sales']],JSON_PRETTY_PRINT));
  }
}

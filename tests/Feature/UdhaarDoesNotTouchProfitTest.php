<?php
namespace Tests\Feature;
/** Collecting udhaar later must change CASH only — never sales or profit (no double counting). */
use App\Models\{User,Product,Customer};
use App\Support\Enums\RoleName;
use App\Services\{ProfitService,CashBookService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class UdhaarDoesNotTouchProfitTest extends TestCase {
  use RefreshDatabase;
  private function props($route,$params=[]){ $p=null; $this->get(route($route,$params))->assertOk()->assertInertia(function($pg) use (&$p){ $p=$pg->toArray()['props']; return $pg; }); return $p; }

  public function test_collection_week_later_changes_cash_only(){
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
    $wh=\App\Models\Warehouse::where('code','MAIN')->value('id');
    $c=Customer::create(['code'=>'C1','name'=>'Shop A','type'=>'wholesale','current_balance'=>0,'is_active'=>true]);

    // Monday: 10 × 6L @70 on udhaar = 700 (cost 550 → profit 150). No cash.
    $this->travelTo(now()->startOfMonth()->addDays(2)); $saleDay=now()->toDateString();
    $this->post(route('sales.store'),['warehouse_id'=>$wh,'type'=>'wholesale','customer_id'=>$c->id,'paid_total'=>0,
      'items'=>[['product_id'=>Product::where('sku','SEAL-6000')->value('id'),'quantity'=>10,'unit_price'=>70]]])->assertSessionHasNoErrors();
    $ps=app(ProfitService::class); $cb=app(CashBookService::class);
    $this->assertEquals([700,150],[$ps->summary($saleDay,$saleDay)['revenue'],$ps->summary($saleDay,$saleDay)['gross_profit']]);
    $this->assertEquals(0,$cb->day($saleDay)['cash']['in']);

    // A week later: customer pays 400, then 300.
    $this->travel(7)->days(); $payDay=now()->toDateString();
    $this->post(route('customers.payment',$c),['amount'=>400,'method'=>'cash'])->assertSessionHasNoErrors();
    $this->post(route('customers.payment',$c),['amount'=>300,'method'=>'bank'])->assertSessionHasNoErrors();

    // Pay day: NO sales, NO profit — only cash moved.
    $pd=$ps->summary($payDay,$payDay);
    $this->assertEquals([0,0,0,0],[$pd['revenue'],$pd['cogs'],$pd['gross_profit'],$pd['net_profit']]);
    $this->assertEquals(400,collect($cb->day($payDay)['cash']['lines'])->firstWhere('kind','udhaar')['amount']);
    $this->assertEquals(300,collect($cb->day($payDay)['bank']['lines'])->firstWhere('kind','udhaar')['amount']);
    // Sale day: unchanged.
    $this->assertEquals([700,150],[$ps->summary($saleDay,$saleDay)['revenue'],$ps->summary($saleDay,$saleDay)['gross_profit']]);
    // Whole month: sale counted ONCE (700, not 1400).
    $m=$ps->summary(now()->startOfMonth()->toDateString(),$payDay);
    $this->assertEquals([700,150,1],[$m['revenue'],$m['gross_profit'],$m['invoices']]);
    $this->assertEquals(0,(float)$c->fresh()->current_balance);

    // Pages: dashboard today (pay day) shows 0 sales / 0 profit; reports month shows 700 sales, 700 received, 0 outstanding, udhaar collected 700.
    $d=$this->props('dashboard');
    $this->assertEquals([0,0],[$d['metrics']['todays_sales']['value'],$d['profit']['today']['net_profit']]);
    $this->assertEquals(700,$d['metrics']['monthly_revenue']['value']);
    $r=$this->props('reports.index',['from'=>now()->startOfMonth()->toDateString(),'to'=>$payDay]);
    $this->assertEquals([700,700,0,700,0],[$r['sales']['total'],$r['sales']['received'],$r['sales']['outstanding'],$r['udhaar_collected'],$r['customer_outstanding']]);
    $this->assertEquals(150,$r['pnl']['gross_profit']);
    $rp=$this->props('reports.index',['from'=>$payDay,'to'=>$payDay]);
    $this->assertEquals([0,0,700],[$rp['sales']['total'],$rp['pnl']['gross_profit'],$rp['udhaar_collected']]);
  }
}

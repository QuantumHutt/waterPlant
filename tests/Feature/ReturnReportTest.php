<?php
namespace Tests\Feature;
use App\Models\{User,Product};
use App\Support\Enums\RoleName;
use App\Services\SaleService;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ReturnReportTest extends TestCase {
  use RefreshDatabase;
  public function test_returned_sale_excluded_from_report_and_dashboard(){
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
    $wh=\App\Models\Warehouse::where('code','MAIN')->first()->id;
    $p=Product::where('sku','SEAL-250')->first();
    $inv=app(SaleService::class)->create(['warehouse_id'=>$wh,'type'=>'pos','items'=>[['product_id'=>$p->id,'quantity'=>5,'unit_price'=>20]]]);

    // Before return: report counts it.
    $r1=$this->get(route('reports.index'));
    $r1->assertInertia(fn($pg)=>$pg->where('sales.total',100));

    // Return the sale.
    app(SaleService::class)->returnInvoice($inv->fresh(), 'test');

    // After return: excluded from totals, shown as a return.
    $r2=$this->get(route('reports.index'));
    $r2->assertInertia(fn($pg)=>$pg->where('sales.total',0)->where('sales.returns_count',1));

    // Dashboard today's sales also drops to 0.
    $m=app(\App\Services\DashboardService::class)->metrics();
    $this->assertSame(0.0,$m['todays_sales']['value']);
  }
}

<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,Invoice};
use App\Support\Enums\RoleName;
use App\Services\SaleService;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PartialUdhaarTest extends TestCase {
  use RefreshDatabase;
  public function test_partial_payment_records_udhaar_per_customer(){
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
    $wh=\App\Models\Warehouse::where('code','MAIN')->first()->id;
    $c=Customer::create(['code'=>'C1','name'=>'Ahmed','type'=>'retail','current_balance'=>0]);
    $p=Product::where('sku','SEAL-500')->first(); // 30 each; 50 = not exact, use qty to hit 50? use unit_price override
    // Sale of Rs 50, customer pays only 40 -> 10 udhaar
    $inv=app(SaleService::class)->create(['warehouse_id'=>$wh,'customer_id'=>$c->id,'type'=>'pos','paid_total'=>40,
      'items'=>[['product_id'=>$p->id,'quantity'=>1,'unit_price'=>50]]]);
    $this->assertSame(50.0,(float)$inv->grand_total);
    $this->assertSame(40.0,(float)$inv->fresh()->paid_total);
    $this->assertSame(10.0,(float)$inv->fresh()->balance);   // 10 udhaar on invoice
    $this->assertSame('partial',$inv->fresh()->status);
    $this->assertSame(10.0,(float)$c->fresh()->current_balance); // tracked on THIS customer
  }
}

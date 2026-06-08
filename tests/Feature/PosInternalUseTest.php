<?php
namespace Tests\Feature;
use App\Models\{User,Product,StockItem,Invoice};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PosInternalUseTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }
  public function test_super_admin_free_internal_use_no_sale(){
    $u=User::factory()->create(); $u->assignRole(RoleName::SuperAdmin->value); $this->actingAs($u);
    $p=Product::where('sku','SEAL-500')->first();
    $before=(float)StockItem::where('product_id',$p->id)->sum('quantity');
    $this->post(route('consumption.pos'),['warehouse_id'=>$this->wh(),'items'=>[['product_id'=>$p->id,'quantity'=>6]]])
      ->assertSessionHasNoErrors();
    $this->assertSame($before-6,(float)StockItem::where('product_id',$p->id)->sum('quantity'));
    $this->assertSame(0,Invoice::count()); // no sale
  }
  public function test_cashier_cannot_use_pos_internal_use(){
    $u=User::factory()->create(); $u->assignRole(RoleName::SalesManager->value); $this->actingAs($u);
    $p=Product::where('sku','SEAL-500')->first();
    $this->post(route('consumption.pos'),['warehouse_id'=>$this->wh(),'items'=>[['product_id'=>$p->id,'quantity'=>1]]])
      ->assertForbidden();
  }
}

<?php
namespace Tests\Feature;
use App\Models\{User,Product,Warehouse,Supplier};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,SupplierSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,SupplierSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
  }
  private function as(RoleName $r){ $u=User::factory()->create(); $u->assignRole($r->value); $this->actingAs($u); return $u; }
  private function wh(){ return Warehouse::where('code','MAIN')->first()->id; }
  private function pid(){ return Product::where('sku','SEAL-500')->first()->id; }
  private function stockIn(){ return $this->post(route('inventory.stock-in'),['warehouse_id'=>$this->wh(),'product_id'=>$this->pid(),'quantity'=>5,'unit_cost'=>8]); }
  private function purchase(){ return $this->post(route('purchases.store'),['supplier_id'=>Supplier::first()->id,'warehouse_id'=>$this->wh(),'items'=>[['product_id'=>$this->pid(),'quantity'=>10,'unit_cost'=>8]]]); }

  public function test_admin_can_inventory_and_purchase(){ $this->as(RoleName::Admin); $this->stockIn()->assertSessionHasNoErrors(); $this->purchase()->assertSessionHasNoErrors(); }
  public function test_manager_can_inventory_and_purchase(){ $this->as(RoleName::Manager); $this->stockIn()->assertSessionHasNoErrors(); $this->purchase()->assertSessionHasNoErrors(); }
  public function test_accountant_purchase_yes_inventory_no(){ $this->as(RoleName::Accountant); $this->stockIn()->assertForbidden(); $this->purchase()->assertSessionHasNoErrors(); }
  public function test_sales_manager_inventory_no_purchase_no(){ $this->as(RoleName::SalesManager); $this->stockIn()->assertForbidden(); $this->purchase()->assertForbidden(); }
  public function test_production_manager_inventory_no_create_purchase_no(){ $this->as(RoleName::ProductionManager); $this->stockIn()->assertForbidden(); $this->purchase()->assertForbidden(); }
  public function test_delivery_staff_blocked(){ $this->as(RoleName::DeliveryStaff); $this->stockIn()->assertForbidden(); $this->purchase()->assertForbidden(); }
  public function test_super_admin_all(){ $this->as(RoleName::SuperAdmin); $this->stockIn()->assertSessionHasNoErrors(); $this->purchase()->assertSessionHasNoErrors(); }
}

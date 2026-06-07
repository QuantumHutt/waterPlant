<?php
namespace Tests\Feature;
use App\Models\{User,Product,ProductCategory};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,ProductSeeder,SettingSeeder,WarehouseSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProdWebTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    $this->seed(RolePermissionSeeder::class); $this->seed(SettingSeeder::class);
    $this->seed(WarehouseSeeder::class); $this->seed(ProductSeeder::class);
    $u=User::factory()->create(); $u->assignRole(RoleName::SuperAdmin->value); $this->actingAs($u);
  }
  public function test_web_create(){
    $cat=ProductCategory::first();
    $r=$this->post(route('products.store'),[
      'category_id'=>$cat->id,'name'=>'Test 2L','type'=>'finished','unit'=>'pcs',
      'cost_price'=>10,'sale_price'=>40,'is_active'=>true,
      'variants'=>[['name'=>'2L','capacity_ml'=>2000,'cost_price'=>10,'sale_price'=>40]],
    ]);
    $r->assertSessionHasNoErrors(); $r->assertRedirect();
    $this->assertDatabaseHas('products',['name'=>'Test 2L']);
  }
  public function test_web_update(){
    $p=Product::where('sku','SEAL-500')->first();
    $r=$this->put(route('products.update',$p->id),['name'=>'500ml New','sale_price'=>35]);
    $r->assertSessionHasNoErrors(); $r->assertRedirect();
  }
  public function test_web_delete(){
    $p=Product::where('sku','SEAL-250')->first();
    $this->delete(route('products.destroy',$p->id))->assertRedirect();
  }
}

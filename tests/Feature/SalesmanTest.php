<?php
namespace Tests\Feature;
use App\Models\{User,Product,Invoice};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,SalesmanSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class SalesmanTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
  }
  private function salesman(){ $u=User::factory()->create(); $u->assignRole(RoleName::Salesman->value); $this->actingAs($u); return $u; }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->first()->id; }

  public function test_seeder_creates_salesman_idempotently(){
    $this->seed(SalesmanSeeder::class);
    $this->seed(SalesmanSeeder::class);
    $u=User::where('email','umer@gmail.com')->firstOrFail();
    $this->assertTrue($u->hasRole('salesman'));
    $this->assertTrue(Hash::check(env('SALESMAN_PASSWORD','password'),$u->password));
    $this->assertSame(1,User::where('email','umer@gmail.com')->count());
  }

  public function test_salesman_lands_on_pos_and_cannot_reach_owner_pages(){
    $this->salesman();
    $this->get(route('dashboard'))->assertRedirect(route('sales.index'));
    $this->get(route('sales.index'))->assertOk()->assertInertia(fn($pg)=>$pg->where('products.0.cost_price',null));
    $this->get(route('reports.index'))->assertForbidden();
  }

  public function test_salesman_sale_uses_list_price_even_if_tampered(){
    $this->salesman();
    $p=Product::where('sku','SEAL-6000')->first();
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','discount'=>50,
      'items'=>[['product_id'=>$p->id,'quantity'=>2,'unit_price'=>1,'discount'=>5]]])->assertSessionHasNoErrors();
    $inv=Invoice::latest('id')->first();
    $this->assertEquals(200,(float)$inv->grand_total);              // 2 × shop price 100, no discount
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'wholesale',
      'items'=>[['product_id'=>$p->id,'quantity'=>2,'unit_price'=>1]]])->assertSessionHasNoErrors();
    $this->assertEquals(140,(float)Invoice::latest('id')->first()->grand_total); // 2 × market 70
  }

  public function test_salesman_cannot_return_or_take_payment(){
    $this->salesman();
    $p=Product::where('sku','SEAL-6000')->first();
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$p->id,'quantity'=>1,'unit_price'=>100]]]);
    $inv=Invoice::latest('id')->first();
    $this->post(route('sales.return',$inv))->assertForbidden();
    $this->post(route('sales.payment',$inv),['amount'=>10])->assertForbidden();
  }

  public function test_salesman_types_cooler_refill_amount_and_profit_stays_pending(){
    $this->salesman();
    $c=Product::where('sku','COOLER-REFILL')->first();
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[
      ['product_id'=>$c->id,'quantity'=>1,'unit_price'=>20],['product_id'=>$c->id,'quantity'=>1,'unit_price'=>30,'discount'=>5]]])->assertSessionHasNoErrors();
    $inv=Invoice::latest('id')->first();
    $this->assertEquals(50,(float)$inv->grand_total);   // typed amounts kept, discount ignored
    $this->assertNull($inv->items->first()->unit_cost);
    $this->assertSame(0,\App\Models\StockItem::where('product_id',$c->id)->count()); // service, no stock
  }

  public function test_salesman_collects_udhaar_but_cannot_edit_customers(){
    $c=\App\Models\Customer::create(['code'=>'C9','name'=>'Shop B','type'=>'wholesale','current_balance'=>0,'is_active'=>true]);
    $owner=User::factory()->create(); $owner->assignRole(RoleName::Manager->value); $this->actingAs($owner);
    $p=Product::where('sku','SEAL-6000')->first();
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'wholesale','customer_id'=>$c->id,'paid_total'=>0,
      'items'=>[['product_id'=>$p->id,'quantity'=>5,'unit_price'=>70]]]);   // 350 udhaar

    $this->salesman();
    $this->get(route('sales.index'))->assertInertia(fn($pg)=>$pg->where('customers.0.current_balance','350.00'));
    $this->post(route('customers.payment',$c),['amount'=>200,'method'=>'cash'])->assertSessionHas('success');
    $this->assertEquals(150,(float)$c->fresh()->current_balance);
    $this->assertEquals(150,(float)Invoice::latest('id')->first()->balance);
    $this->put(route('customers.update',$c),['name'=>'Hacked','type'=>'retail'])->assertForbidden();

    $d=User::factory()->create(); $d->assignRole(RoleName::DeliveryStaff->value); $this->actingAs($d);
    $this->post(route('customers.payment',$c),['amount'=>50])->assertForbidden();
  }

  public function test_owner_can_still_give_a_manual_price(){
    $u=User::factory()->create(); $u->assignRole(RoleName::Manager->value); $this->actingAs($u);
    $p=Product::where('sku','SEAL-6000')->first();
    $this->post(route('sales.store'),['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>[['product_id'=>$p->id,'quantity'=>1,'unit_price'=>80]]]);
    $this->assertEquals(80,(float)Invoice::latest('id')->first()->grand_total);
  }
}

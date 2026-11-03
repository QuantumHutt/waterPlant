<?php
namespace Tests\Feature;
/** Every role × every money route: allowed roles get through, everyone else gets 403. */
use App\Models\{User,Product,Customer,Supplier,Invoice,Expense,ExpenseCategory};
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,ExpenseSetupSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PermissionSweepTest extends TestCase {
  use RefreshDatabase;
  public function test_role_matrix_on_money_routes(){
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class,ExpenseSetupSeeder::class] as $s) $this->seed($s);
    $wh=\App\Models\Warehouse::where('code','MAIN')->value('id');
    $admin=User::factory()->create(); $admin->assignRole(RoleName::Admin->value); $this->actingAs($admin);
    $c=Customer::create(['code'=>'C1','name'=>'C','type'=>'retail','current_balance'=>500,'is_active'=>true]);
    $sup=Supplier::create(['code'=>'S1','name'=>'S','current_balance'=>500,'is_active'=>true]);
    $p6=Product::where('sku','SEAL-6000')->value('id');
    $cat=ExpenseCategory::where('name','Rent')->value('id');
    // Fresh bill + expense for every role (an allowed role may cancel / delete them).
    $fresh=function() use ($admin,$wh,$c,$p6,$cat){
      $this->actingAs($admin);
      $this->post(route('sales.store'),['warehouse_id'=>$wh,'type'=>'pos','customer_id'=>$c->id,'paid_total'=>0,'items'=>[['product_id'=>$p6,'quantity'=>1,'unit_price'=>100]]]);
      $this->post(route('expenses.store'),['category_id'=>$cat,'amount'=>10,'paid_via'=>'cash']);
      return [Invoice::latest('id')->first(),Expense::latest('id')->first()];
    };
    [$inv,$exp]=$fresh();

    // route => [method, params, payload, roles that may use it]
    $routes=[
      'sales.index'       => ['get', [], [], ['admin','manager','accountant','sales-manager','delivery-staff','salesman']],
      'sales.store'       => ['post',[], ['warehouse_id'=>$wh,'type'=>'pos','items'=>[['product_id'=>$p6,'quantity'=>1,'unit_price'=>100]]], ['admin','manager','sales-manager','salesman']],
      'sales.payment'     => ['post','inv', ['amount'=>1], ['admin','manager','sales-manager']],
      'sales.return'      => ['post','inv', ['items'=>[]], ['admin','manager','sales-manager']],
      'sales.cancel'      => ['post','inv', ['reason'=>'x'], ['admin','manager','sales-manager']],
      'customers.payment' => ['post',[$c], ['amount'=>1], ['admin','manager','sales-manager','salesman']],
      'customers.advance' => ['post',[$c], ['amount'=>1], ['admin','manager','sales-manager','salesman']],
      'customers.update'  => ['put', [$c], ['name'=>'C','type'=>'retail'], ['admin','manager','sales-manager']],
      'expenses.index'    => ['get', [], [], ['admin','manager','accountant']],
      'expenses.store'    => ['post',[], ['category_id'=>$cat,'amount'=>1,'paid_via'=>'cash'], ['admin','accountant','salesman']],   // manager: view only by default
      'expenses.update'   => ['put', 'exp', ['category_id'=>$cat,'amount'=>1,'paid_via'=>'cash'], ['admin','accountant']],
      'expenses.destroy'  => ['delete','exp', [], ['admin','accountant']],
      'purchases.store'   => ['post',[], ['supplier_id'=>$sup->id,'warehouse_id'=>$wh,'items'=>[['product_id'=>Product::where('sku','CAP-STD')->value('id'),'quantity'=>1,'unit_cost'=>1]]], ['admin','manager','accountant']],
      'suppliers.payment' => ['post',[$sup], ['amount'=>1], ['admin','manager','accountant']],
      'cash.index'        => ['get', [], [], ['admin','manager','accountant','sales-manager','salesman']],
      'cash.transfer'     => ['post',[], ['direction'=>'deposit','amount'=>1], ['admin','manager','accountant','sales-manager']],
      'cash.close'        => ['post',[], ['date'=>now()->toDateString(),'counted_cash'=>0], ['admin','manager','accountant','sales-manager','salesman']],
      'cash.float'        => ['post',[], ['float'=>500], ['admin','manager','accountant','sales-manager']],
      'reports.index'     => ['get', [], [], ['admin','manager','accountant','production-manager','sales-manager']],
      'roles.index'       => ['get', [], [], ['admin','manager']],
      'users.index'       => ['get', [], [], ['admin','manager']],
    ];
    $roles=['admin','manager','accountant','production-manager','sales-manager','delivery-staff','salesman'];
    $failures=[];
    foreach($roles as $role){
      $u=User::factory()->create(); $u->assignRole($role);
      foreach($routes as $name=>[$method,$params,$payload,$allowed]){
        if($params==='inv'||$params==='exp'){ [$inv,$exp]=$fresh(); $params=[$params==='inv'?$inv:$exp]; }
        $r=$this->actingAs($u)->{$method}(route($name,$params),$payload);
        $forbidden=$r->status()===403;
        if(in_array($role,$allowed,true) && $forbidden) $failures[]="$role should reach $name";
        if(!in_array($role,$allowed,true) && !$forbidden) $failures[]="$role must NOT reach $name (got {$r->status()})";
      }
    }
    $this->assertSame([],$failures,implode("\n",$failures));
  }
}

<?php
namespace Tests\Feature;
use App\Models\{User,Product,Expense,ExpenseCategory,JournalLine};
use App\Support\Enums\RoleName;
use App\Services\{CashBookService,ProfitService,InventoryService,ProductionService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,ExpenseSetupSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ExpenseFixAndProductionCostTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class,ExpenseSetupSeeder::class] as $s) $this->seed($s);
  }
  private function as($role){ $u=User::factory()->create(); $u->assignRole($role->value); $this->actingAs($u); return $u; }
  private function cat($n){ return ExpenseCategory::where('name',$n)->value('id'); }
  private function today(){ return now()->toDateString(); }
  private function cashAccountBalance(){ $id=\App\Models\Account::byCode('1000')->id; return (float)JournalLine::where('account_id',$id)->sum('debit')-(float)JournalLine::where('account_id',$id)->sum('credit'); }

  public function test_owner_fixes_salesman_typo_and_cash_profit_follow(){
    config(['audit.console' => true]);   // the web app audits; tests run from the console
    $this->as(RoleName::Salesman);
    $this->post(route('expenses.store'),['category_id'=>$this->cat('Refreshments (Tea / Food)'),'amount'=>500,'paid_via'=>'cash','note'=>'chai']);
    $e=Expense::first();
    $this->put(route('expenses.update',$e),['category_id'=>$e->category_id,'amount'=>50,'paid_via'=>'cash'])->assertForbidden(); // salesman can't edit
    $this->as(RoleName::Admin);
    $this->put(route('expenses.update',$e),['category_id'=>$e->category_id,'amount'=>50,'paid_via'=>'cash'])->assertSessionHas('success');
    $this->assertEquals(50,(float)$e->fresh()->amount);
    $this->assertEquals(-50,app(CashBookService::class)->day($this->today())['cash']['closing']);
    $this->assertEquals(50,app(ProfitService::class)->summary($this->today(),$this->today())['expenses']);
    $this->assertEquals(-50,$this->cashAccountBalance());                                    // journal corrected
    $this->assertTrue(\OwenIt\Auditing\Models\Audit::where('auditable_id',$e->id)->where('event','updated')->exists()); // change logged
  }

  public function test_owner_deletes_wrong_expense(){
    $this->as(RoleName::Admin);
    $this->post(route('expenses.store'),['category_id'=>$this->cat('Rent'),'amount'=>300,'paid_via'=>'cash']);
    $e=Expense::first();
    $this->delete(route('expenses.destroy',$e))->assertSessionHas('success');
    $this->assertSame(0,Expense::count());
    $this->assertEquals(0,app(CashBookService::class)->day($this->today())['cash']['closing']);
    $this->assertEquals(0,$this->cashAccountBalance());
  }

  public function test_production_sets_real_cost_of_filled_bottles(){
    $this->as(RoleName::Admin);
    $wh=\App\Models\Warehouse::where('code','MAIN')->value('id');
    $inv=app(InventoryService::class);
    $inv->stockIn($wh,Product::where('sku','BTL-1500')->value('id'),100,28);
    $inv->stockIn($wh,Product::where('sku','CAP-STD')->value('id'),100,1);
    app(ProductionService::class)->create(['warehouse_id'=>$wh,'consumptions'=>[
        ['product_id'=>Product::where('sku','BTL-1500')->value('id'),'quantity'=>60],
        ['product_id'=>Product::where('sku','CAP-STD')->value('id'),'quantity'=>60],
        ['product_id'=>Product::where('sku','WATER-BULK')->value('id'),'quantity'=>90]],   // 90 L × 1.5
      'outputs'=>[['product_id'=>Product::where('sku','SEAL-1500')->value('id'),'quantity'=>60]]]);
    // (60×28 + 60×1 + 90×1.5) / 60 = 31.25
    $this->assertEquals(31.25,(float)Product::where('sku','SEAL-1500')->value('cost_price'));
    $this->assertEquals(187.5,(float)Product::where('sku','PACK-1500-6')->value('cost_price')); // 6 × 31.25
  }
}

<?php
namespace Tests\Feature;
use App\Models\{User,Product,Customer,Invoice,Payment};
use App\Support\Enums\RoleName;
use App\Services\{SaleService,LedgerService,DashboardService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class UdhaarCollectionTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
  }
  private function customer($opening=0){ return Customer::create(['code'=>'C'.random_int(100,999),'name'=>'Shop A','type'=>'wholesale','current_balance'=>$opening,'is_active'=>true]); }
  private function udhaar($c,$amount){
    return app(SaleService::class)->create(['warehouse_id'=>\App\Models\Warehouse::where('code','MAIN')->value('id'),'customer_id'=>$c->id,'type'=>'wholesale','paid_total'=>0,
      'items'=>[['product_id'=>Product::where('sku','SEAL-6000')->value('id'),'quantity'=>$amount/100,'unit_price'=>100]]]);
  }
  private function outstanding(){ return app(DashboardService::class)->metrics()['outstanding_receivables']['value']; }

  public function test_collecting_udhaar_settles_oldest_invoices_and_outstanding_drops(){
    $c=$this->customer();
    $a=$this->udhaar($c,500); $b=$this->udhaar($c,300);
    $this->assertEquals(800,$this->outstanding());
    $this->post(route('customers.payment',$c),['amount'=>600,'method'=>'cash'])->assertSessionHas('success');
    $this->assertSame(['paid',0.0],[$a->fresh()->status,(float)$a->fresh()->balance]);
    $this->assertSame(['partial',200.0],[$b->fresh()->status,(float)$b->fresh()->balance]);
    $this->assertEquals(200,(float)$c->fresh()->current_balance);
    $this->assertEquals(200,$this->outstanding());                          // ← the reported bug
    $this->assertEquals(600,(float)Payment::where('direction','in')->sum('amount')); // cash recorded
  }

  public function test_invoice_payment_also_reduces_customer_balance(){
    $c=$this->customer(); $inv=$this->udhaar($c,500);
    $this->post(route('sales.payment',$inv),['amount'=>200])->assertSessionHasNoErrors();
    $this->assertEquals(300,(float)$c->fresh()->current_balance);
    $this->assertEquals(300,(float)$inv->fresh()->balance);
    $this->assertEquals(300,$this->outstanding());
  }

  public function test_payment_on_opening_balance_and_overpayment(){
    $c=$this->customer(1000);
    $this->post(route('customers.payment',$c),['amount'=>400]);
    $this->assertEquals(600,(float)$c->fresh()->current_balance);           // no invoice — on account
    $inv=$this->udhaar($c,200);
    $this->post(route('customers.payment',$c),['amount'=>900]);             // 200 to invoice, 700 on account
    $this->assertSame('paid',$inv->fresh()->status);
    $this->assertEquals(-100,(float)$c->fresh()->current_balance);          // 100 advance
  }

  public function test_partial_pos_sale_still_tracks_once(){
    $c=$this->customer();
    app(SaleService::class)->create(['warehouse_id'=>\App\Models\Warehouse::where('code','MAIN')->value('id'),'customer_id'=>$c->id,'type'=>'pos','paid_total'=>40,
      'items'=>[['product_id'=>Product::where('sku','SEAL-6000')->value('id'),'quantity'=>1,'unit_price'=>100]]]);
    $this->assertEquals(60,(float)$c->fresh()->current_balance);           // not double-credited
  }

  public function test_reconcile_fixes_old_style_collections(){
    $c=$this->customer(); $inv=$this->udhaar($c,500);
    app(LedgerService::class)->post($c->fresh(),0,500,null,'Payment received');   // old behaviour: ledger only
    $this->assertEquals(500,(float)$inv->fresh()->balance);
    $this->artisan('udhaar:reconcile')->assertSuccessful();                        // dry run
    $this->assertEquals(500,(float)$inv->fresh()->balance);
    $this->artisan('udhaar:reconcile --apply')->assertSuccessful();
    $this->assertSame(['paid',0.0],[$inv->fresh()->status,(float)$inv->fresh()->balance]);
    $this->assertEquals(0,(float)$c->fresh()->current_balance);
    $this->artisan('udhaar:reconcile --apply')->expectsOutputToContain('Everything already matches')->assertSuccessful();
  }

  public function test_reconcile_adds_missing_ledger_credit_for_invoice_payments(){
    $c=$this->customer(); $inv=$this->udhaar($c,500);
    Payment::create(['reference'=>'RCV-OLD','payable_type'=>$inv->getMorphClass(),'payable_id'=>$inv->id,'amount'=>500,'method'=>'cash','direction'=>'in','paid_at'=>now()->toDateString()]);
    $inv->update(['paid_total'=>500,'balance'=>0,'status'=>'paid']);              // old behaviour: invoice only
    $this->assertEquals(500,(float)$c->fresh()->current_balance);
    $this->artisan('udhaar:reconcile --apply')->assertSuccessful();
    $this->assertEquals(0,(float)$c->fresh()->current_balance);
  }
}

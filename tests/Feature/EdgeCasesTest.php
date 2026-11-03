<?php
namespace Tests\Feature;
/** Mistakes and abuse on POS / purchases / expenses / cash book must be refused cleanly — never saved half-way. */
use App\Models\{User,Product,Customer,Supplier,Invoice,Expense,StockItem,Payment,CashTransfer,ExpenseCategory,PurchaseOrder};
use App\Support\Enums\RoleName;
use App\Services\{CashBookService,SaleService};
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder,ExpenseSetupSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class EdgeCasesTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class,ExpenseSetupSeeder::class] as $s) $this->seed($s);
    $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); $this->actingAs($u);
  }
  private function wh(){ return \App\Models\Warehouse::where('code','MAIN')->value('id'); }
  private function pid($sku){ return Product::where('sku',$sku)->value('id'); }
  private function stock($sku){ return (float)StockItem::where('product_id',$this->pid($sku))->sum('quantity'); }
  private function sale($items,$extra=[]){ return $this->post(route('sales.store'),array_merge(['warehouse_id'=>$this->wh(),'type'=>'pos','items'=>$items],$extra)); }
  private function line($sku,$qty,$price,$extra=[]){ return array_merge(['product_id'=>$this->pid($sku),'quantity'=>$qty,'unit_price'=>$price],$extra); }
  private function customer(){ return Customer::create(['code'=>'C'.random_int(1,999),'name'=>'Cust','type'=>'retail','current_balance'=>0,'is_active'=>true]); }
  private function drawer(){ return app(CashBookService::class)->day(now()->toDateString())['cash']['closing']; }

  // ---------------- POS / SALES ----------------
  public function test_bad_quantities_and_prices_are_refused(){
    $this->sale([$this->line('SEAL-6000',0,100)])->assertSessionHasErrors('items.0.quantity');
    $this->sale([$this->line('SEAL-6000',-2,100)])->assertSessionHasErrors('items.0.quantity');
    $this->sale([$this->line('SEAL-6000',1,-5)])->assertSessionHasErrors('items.0.unit_price');
    $this->sale([])->assertSessionHasErrors('items');
    $this->assertSame(0,Invoice::count());
    $this->assertEquals(300,$this->stock('SEAL-6000'));
  }

  public function test_inactive_or_deleted_product_cannot_be_sold(){
    Product::find($this->pid('SEAL-500'))->update(['is_active'=>false]);
    $this->sale([$this->line('SEAL-500',1,50)])->assertSessionHasErrors('items.0.product_id');
    Product::find($this->pid('SEAL-1500'))->delete();
    $this->sale([$this->line('SEAL-1500',1,100)])->assertSessionHasErrors('items.0.product_id');
    $this->assertSame(0,Invoice::count());
  }

  public function test_walk_in_cannot_leave_money_owing(){
    $this->sale([$this->line('SEAL-6000',2,100)],['paid_total'=>50])->assertSessionHasErrors('customer_id');
    $this->sale([$this->line('SEAL-6000',2,100)],['type'=>'credit'])->assertSessionHasErrors('customer_id');
    $this->assertSame(0,Invoice::count());
    $this->assertEquals(300,$this->stock('SEAL-6000'));                     // nothing deducted
    $c=$this->customer();
    $this->sale([$this->line('SEAL-6000',2,100)],['customer_id'=>$c->id,'paid_total'=>50])->assertSessionHasNoErrors();
    $this->assertEquals(150,(float)$c->fresh()->current_balance);
  }

  public function test_discounts_and_paid_are_clamped_never_negative(){
    $this->sale([$this->line('SEAL-6000',1,100)],['paid_total'=>-50])->assertSessionHasErrors('paid_total');
    $this->sale([$this->line('SEAL-6000',1,100,['discount'=>500])],['discount'=>999,'paid_total'=>0])->assertSessionHasNoErrors();
    $inv=Invoice::latest('id')->first();
    $this->assertEquals([0,0,0,'paid'],[(float)$inv->subtotal,(float)$inv->grand_total,(float)$inv->paid_total,$inv->status]);
    $this->assertEquals(0,$this->drawer());
  }

  public function test_oversell_single_and_pack_saves_nothing(){
    $r=$this->sale([$this->line('SEAL-6000',2,100),$this->line('SEAL-6000',999,100)]);
    $r->assertSessionHas('error');
    $this->assertSame(0,Invoice::count());                                   // first line rolled back too
    $this->assertEquals(300,$this->stock('SEAL-6000'));
    StockItem::where('product_id',$this->pid('SEAL-500'))->update(['quantity'=>5]);
    $this->sale([$this->line('PACK-500-12',1,420)])->assertSessionHas('error');  // needs 12 bottles, has 5
    $this->assertEquals(5,$this->stock('SEAL-500'));
  }

  public function test_returns_cannot_exceed_sold_or_happen_twice(){
    $this->sale([$this->line('SEAL-6000',3,100)]); $inv=Invoice::latest('id')->first(); $item=$inv->items->first();
    $this->post(route('sales.return',$inv),['items'=>[$item->id=>5]])->assertSessionHasErrors('items');
    $this->post(route('sales.return',$inv),['items'=>[$item->id=>0]])->assertSessionHasErrors('items');
    $this->post(route('sales.return',$inv),['items'=>[$item->id=>-1]])->assertSessionHasErrors();
    $this->assertEquals(297,$this->stock('SEAL-6000'));
    $this->post(route('sales.return',$inv),[])->assertSessionHas('success');  // whole bill
    $this->assertSame('returned',$inv->fresh()->status);
    $this->post(route('sales.return',$inv),[])->assertSessionHasErrors('invoice');
    $this->post(route('sales.cancel',$inv),['reason'=>'x'])->assertSessionHasErrors('invoice');
    $this->assertEquals(300,$this->stock('SEAL-6000'));
    $this->assertEquals(0,$this->drawer());                                   // 300 in, 300 refunded
    $this->post(route('sales.payment',$inv),['amount'=>10])->assertSessionHasErrors('amount'); // nothing owed
  }

  public function test_advance_refund_needs_customer_and_bottle_exchange_validates(){
    $this->sale([$this->line('SEAL-6000',1,100)]); $inv=Invoice::latest('id')->first();
    $this->post(route('sales.return',$inv),['refund_method'=>'advance'])->assertSessionHasErrors('refund_method');
    $this->sale([$this->line('19L-REFILL',2,100)],['type'=>'refill','bottles_returned'=>-1])->assertSessionHasErrors('bottles_returned');
    $this->sale([$this->line('19L-REFILL',2,100)],['type'=>'refill','bottles_returned'=>1])->assertSessionHasErrors('customer_id');
    $this->assertEquals(100,$this->stock('19L-BOTTLE'));
  }

  public function test_double_submit_same_sale_makes_two_bills_not_a_crash(){
    // Two identical posts (double-click) each succeed with unique invoice numbers — no collision error.
    $this->sale([$this->line('SEAL-6000',1,100)])->assertSessionHasNoErrors();
    $this->sale([$this->line('SEAL-6000',1,100)])->assertSessionHasNoErrors();
    $this->assertSame(2,Invoice::distinct('invoice_number')->count('invoice_number'));
  }

  // ---------------- PURCHASES / SUPPLIERS ----------------
  public function test_purchase_validation_receive_once_and_paid_clamped(){
    $sup=Supplier::create(['code'=>'S1','name'=>'Sup','current_balance'=>0,'is_active'=>true]);
    $base=['supplier_id'=>$sup->id,'warehouse_id'=>$this->wh()];
    $this->post(route('purchases.store'),$base+['items'=>[['product_id'=>$this->pid('BTL-1500'),'quantity'=>0,'unit_cost'=>28]]])->assertSessionHasErrors('items.0.quantity');
    $this->post(route('purchases.store'),$base+['items'=>[['product_id'=>$this->pid('BTL-1500'),'quantity'=>5,'unit_cost'=>-1]]])->assertSessionHasErrors('items.0.unit_cost');
    $this->post(route('purchases.store'),$base+['items'=>[]])->assertSessionHasErrors('items');
    $this->assertSame(0,PurchaseOrder::count());
    // Draft, then receive twice → stock added once, supplier charged once.
    $this->post(route('purchases.store'),$base+['items'=>[['product_id'=>$this->pid('BTL-1500'),'quantity'=>10,'unit_cost'=>28]]])->assertSessionHasNoErrors();
    $po=PurchaseOrder::first();
    $this->post(route('purchases.receive',$po))->assertSessionHasNoErrors();
    $this->post(route('purchases.receive',$po))->assertSessionHasNoErrors();
    $this->assertEquals([10,280],[$this->stock('BTL-1500'),(float)$sup->fresh()->current_balance]);
    // Paid more than the bill → only the bill amount is taken.
    $this->sale([$this->line('SEAL-6000',10,100)]);                          // 1000 in drawer
    $this->post(route('purchases.store'),$base+['receive_now'=>true,'paid_amount'=>9999,'paid_method'=>'cash','items'=>[['product_id'=>$this->pid('CAP-STD'),'quantity'=>100,'unit_cost'=>1]]])->assertSessionHasNoErrors();
    $this->assertEquals([280,900],[(float)$sup->fresh()->current_balance,$this->drawer()]);
    $this->post(route('suppliers.payment',$sup),['amount'=>0])->assertSessionHasErrors('amount');
    $this->post(route('suppliers.payment',$sup),['amount'=>281])->assertSessionHasErrors('amount');
  }

  // ---------------- EXPENSES ----------------
  public function test_expense_validation_and_past_date_lands_on_that_day(){
    $cat=ExpenseCategory::where('name','Rent')->value('id');
    $this->post(route('expenses.store'),['category_id'=>$cat,'amount'=>0,'paid_via'=>'cash'])->assertSessionHasErrors('amount');
    $this->post(route('expenses.store'),['category_id'=>$cat,'amount'=>-5,'paid_via'=>'cash'])->assertSessionHasErrors('amount');
    $this->post(route('expenses.store'),['category_id'=>999,'amount'=>5,'paid_via'=>'cash'])->assertSessionHasErrors('category_id');
    $this->post(route('expenses.store'),['category_id'=>$cat,'amount'=>5,'paid_via'=>'wallet'])->assertSessionHasErrors('paid_via');
    $this->assertSame(0,Expense::count());
    $past=now()->subDays(3)->toDateString();
    $this->post(route('expenses.store'),['category_id'=>$cat,'amount'=>150,'paid_via'=>'cash','spent_at'=>$past])->assertSessionHasNoErrors();
    $cb=app(CashBookService::class);
    $this->assertEquals(150,collect($cb->day($past)['cash']['lines'])->firstWhere('kind','expense')['amount']);
    $this->assertEquals(-150,$cb->day(now()->toDateString())['cash']['opening']);    // carried forward
    $this->delete(route('expenses.destroy',9999))->assertNotFound();
  }

  // ---------------- CASH BOOK ----------------
  public function test_cash_book_limits(){
    $this->sale([$this->line('SEAL-6000',5,100)]);                            // 500 in drawer
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>0])->assertSessionHasErrors('amount');
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>501])->assertSessionHasErrors('amount');
    $this->post(route('cash.transfer'),['direction'=>'withdraw','amount'=>1])->assertSessionHasErrors('amount');   // bank is 0
    $this->post(route('cash.transfer'),['direction'=>'sideways','amount'=>1])->assertSessionHasErrors('direction');
    $this->assertSame(0,CashTransfer::count());
    // Past-dated deposit is checked against THAT day's drawer, not today's.
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>100,'date'=>now()->subDays(2)->toDateString()])->assertSessionHasErrors('amount');
    $this->post(route('cash.transfer'),['direction'=>'deposit','amount'=>500])->assertSessionHasNoErrors();
    $this->assertEquals([0,500],[$this->drawer(),app(CashBookService::class)->day(now()->toDateString())['bank']['closing']]);
    $this->post(route('cash.close'),['date'=>now()->toDateString(),'counted_cash'=>-1])->assertSessionHasErrors('counted_cash');
    $this->post(route('cash.close'),['date'=>'not-a-date','counted_cash'=>0])->assertSessionHasErrors('date');
    $this->post(route('cash.float'),['float'=>-10])->assertSessionHasErrors('float');
    $h=app(CashBookService::class)->hisaab();
    $this->assertEquals([0,0],[$h['drawer_now'],$h['suggested_deposit']]);
  }

  public function test_closing_a_day_then_adding_an_expense_shows_new_difference(){
    $this->sale([$this->line('SEAL-6000',5,100)]);
    $today=now()->toDateString();
    $this->post(route('cash.close'),['date'=>$today,'counted_cash'=>500]);
    $this->assertEquals(0,(float)\App\Models\CashClosing::first()->difference);
    $this->post(route('expenses.store'),['category_id'=>ExpenseCategory::where('name','Rent')->value('id'),'amount'=>100,'paid_via'=>'cash']);
    // Closing is a snapshot; re-close to refresh it.
    $this->assertEquals(400,$this->drawer());
    $this->post(route('cash.close'),['date'=>$today,'counted_cash'=>500]);
    $this->assertEquals([1,100],[\App\Models\CashClosing::count(),(float)\App\Models\CashClosing::first()->difference]);  // one row per day, updated
  }

  public function test_money_never_appears_twice_in_the_cash_book(){
    $c=$this->customer();
    $this->sale([$this->line('SEAL-6000',10,100)],['customer_id'=>$c->id,'paid_total'=>300]);   // 300 now, 700 udhaar
    $this->post(route('customers.payment',$c),['amount'=>700,'method'=>'cash']);
    $this->post(route('customers.advance',$c),['amount'=>200]);
    $this->sale([$this->line('SEAL-6000',1,100)],['customer_id'=>$c->id]);                    // paid from advance
    $this->assertEquals(1200,$this->drawer());                                                 // 300 + 700 + 200, advance use adds nothing
    $this->assertEquals(1200,(float)Payment::where('direction','in')->where('method','!=','advance')->sum('amount'));
  }
}

<?php
namespace Tests\Feature;
use App\Models\User;
use App\Support\Enums\RoleName;
use Database\Seeders\{RolePermissionSeeder,SettingSeeder,WarehouseSeeder,ProductSeeder,OpeningStockSeeder,ChartOfAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class AccessControlTest extends TestCase {
  use RefreshDatabase;
  protected function setUp(): void {
    parent::setUp();
    foreach([RolePermissionSeeder::class,SettingSeeder::class,WarehouseSeeder::class,ProductSeeder::class,OpeningStockSeeder::class,ChartOfAccountsSeeder::class] as $s) $this->seed($s);
  }
  private function admin(){ $u=User::factory()->create(); $u->assignRole(RoleName::Admin->value); return $u; }

  public function test_owner_gives_umer_extra_permission(){
    $umer=User::factory()->create(['email'=>'umer@example.test']); $umer->assignRole(RoleName::Salesman->value);
    $this->actingAs($umer)->get(route('dashboard'))->assertRedirect(route('sales.index'));
    $this->actingAs($this->admin())->put(route('users.update',$umer),['roles'=>['salesman'],'permissions'=>['reports.view','sales.discount']])->assertSessionHasNoErrors();
    $umer->refresh();
    $this->assertTrue($umer->hasRole('salesman'));
    $this->assertTrue($umer->can('reports.view'));
    $this->actingAs($umer)->get(route('dashboard'))->assertOk()->assertInertia(fn($pg)=>$pg->whereNot('profit',null));
    $this->get(route('users.index'))->assertForbidden();                            // still not a manager
    $this->actingAs($this->admin())->put(route('users.update',$umer),['permissions'=>[]]);
    $this->assertFalse($umer->fresh()->can('reports.view'));
  }

  public function test_role_edit_survives_reseeding(){
    $role=Role::findByName('salesman');
    $this->actingAs($this->admin())->put(route('roles.update',$role),['permissions'=>['sales.view','sales.create']])->assertSessionHas('success');
    $this->seed(RolePermissionSeeder::class);
    $this->assertEqualsCanonicalizing(['sales.view','sales.create'],$role->fresh()->permissions->pluck('name')->all());
    $this->put(route('roles.update',Role::findByName('super-admin')),['permissions'=>[]])->assertStatus(422);
  }

  public function test_roles_page_needs_permission(){
    $s=User::factory()->create(); $s->assignRole(RoleName::Salesman->value);
    $this->actingAs($s)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($this->admin())->get(route('roles.index'))->assertOk()->assertInertia(fn($pg)=>$pg->has('roles')->has('permissionGroups'));
  }
}

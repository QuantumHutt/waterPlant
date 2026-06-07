<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Enums\RoleName;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_admin_can_create_a_user_via_api(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'New Operator',
                'email' => 'operator@waterplant.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'roles' => [RoleName::ProductionManager->value],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'operator@waterplant.test']);

        $created = User::where('email', 'operator@waterplant.test')->first();
        $this->assertTrue($created->hasRole(RoleName::ProductionManager->value));
    }

    public function test_password_is_hashed_on_create(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Hash Check',
                'email' => 'hash@waterplant.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'roles' => [RoleName::SalesManager->value],
            ])->assertCreated();

        $user = User::where('email', 'hash@waterplant.test')->first();
        $this->assertNotSame('Password123!', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Password123!', $user->password));
    }

    public function test_non_privileged_user_cannot_list_users(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::DeliveryStaff->value);
        $token = $staff->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/users/{$admin->id}")
            ->assertForbidden();
    }

    public function test_creation_is_validated(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => '',
                'email' => 'not-an-email',
                'roles' => [],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'roles']);
    }
}

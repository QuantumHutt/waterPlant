<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'tester@waterplant.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/v1/login', [
            'email' => 'tester@waterplant.test',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user' => ['id', 'email', 'roles']]]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_fails_with_bad_credentials(): void
    {
        User::factory()->create([
            'email' => 'tester@waterplant.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'tester@waterplant.test',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_disabled_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'off@waterplant.test',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'off@waterplant.test',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')
            ->assertOk();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}

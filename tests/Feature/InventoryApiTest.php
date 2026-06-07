<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Enums\RoleName;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->product = Product::factory()->create();
    }

    private function tokenFor(RoleName $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user->createToken('t')->plainTextToken;
    }

    public function test_manager_can_stock_in_via_api(): void
    {
        $token = $this->tokenFor(RoleName::Manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/stock-in', [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'quantity' => 150,
                'unit_cost' => 12,
            ])->assertOk();

        $this->assertDatabaseHas('stock_items', [
            'product_id' => $this->product->id,
            'quantity' => 150,
        ]);
    }

    public function test_stock_out_beyond_available_returns_422(): void
    {
        $token = $this->tokenFor(RoleName::Manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/stock-out', [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'quantity' => 10,
            ])->assertStatus(422);
    }

    public function test_accountant_cannot_perform_stock_in(): void
    {
        // Accountant has inventory.view but not inventory.create.
        $token = $this->tokenFor(RoleName::Accountant);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/stock-in', [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'quantity' => 10,
            ])->assertForbidden();
    }

    public function test_transfer_requires_different_warehouses(): void
    {
        $token = $this->tokenFor(RoleName::Manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/transfer', [
                'from_warehouse_id' => $this->warehouse->id,
                'to_warehouse_id' => $this->warehouse->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
            ])->assertStatus(422)->assertJsonValidationErrors(['from_warehouse_id']);
    }

    public function test_stock_listing_is_visible_to_viewers(): void
    {
        $token = $this->tokenFor(RoleName::SalesManager); // has inventory.view

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }
}

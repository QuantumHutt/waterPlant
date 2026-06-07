<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Enums\RoleName;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // owen-it disables auditing during console runs by default; enable for the audit assertion.
        config(['audit.console' => true]);
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsRole(RoleName $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user->createToken('t')->plainTextToken;
    }

    public function test_manager_can_create_product_with_variants(): void
    {
        $category = ProductCategory::factory()->create();
        $token = $this->actingAsRole(RoleName::Manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Purified Water',
                'type' => 'finished',
                'unit' => 'pcs',
                'cost_price' => 8,
                'sale_price' => 25,
                'reorder_level' => 100,
                'variants' => [
                    ['name' => '500ml', 'capacity_ml' => 500, 'cost_price' => 8, 'sale_price' => 30],
                    ['name' => '19L Gallon', 'capacity_ml' => 19000, 'cost_price' => 90, 'sale_price' => 250],
                ],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name' => 'Purified Water']);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_sku_is_auto_generated_when_omitted(): void
    {
        $category = ProductCategory::factory()->create();
        $token = $this->actingAsRole(RoleName::Manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'No Sku Product',
                'type' => 'consumable',
                'unit' => 'pcs',
                'cost_price' => 1,
                'sale_price' => 2,
            ])->assertCreated();

        $product = Product::where('name', 'No Sku Product')->first();
        $this->assertNotEmpty($product->sku);
    }

    public function test_updating_variants_syncs_and_removes_missing(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $v1 = $product->variants()->create(['sku' => 'V1', 'name' => '250ml', 'cost_price' => 5, 'sale_price' => 20]);
        $product->variants()->create(['sku' => 'V2', 'name' => '500ml', 'cost_price' => 8, 'sale_price' => 30]);

        $token = $this->actingAsRole(RoleName::Admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$product->id}", [
                'variants' => [
                    ['id' => $v1->id, 'name' => '250ml Updated', 'cost_price' => 6, 'sale_price' => 22],
                ],
            ])->assertOk();

        $this->assertDatabaseHas('product_variants', ['id' => $v1->id, 'name' => '250ml Updated']);
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->create(['sku' => 'DUP-001', 'category_id' => $category->id]);
        $token = $this->actingAsRole(RoleName::Admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'sku' => 'DUP-001',
                'name' => 'Dup',
                'type' => 'finished',
                'unit' => 'pcs',
                'cost_price' => 1,
                'sale_price' => 2,
            ])->assertStatus(422)->assertJsonValidationErrors(['sku']);
    }

    public function test_delivery_staff_cannot_create_products(): void
    {
        $category = ProductCategory::factory()->create();
        $token = $this->actingAsRole(RoleName::DeliveryStaff);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'X',
                'type' => 'finished',
                'unit' => 'pcs',
                'cost_price' => 1,
                'sale_price' => 2,
            ])->assertForbidden();
    }

    public function test_product_is_audited_on_update(): void
    {
        $product = Product::factory()->create();
        $token = $this->actingAsRole(RoleName::Admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$product->id}", ['sale_price' => 999])
            ->assertOk();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'event' => 'updated',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductService extends BaseService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /** @param array<string,mixed> $filters */
    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->products->paginate($perPage, $filters, ['category', 'variants']);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): Product
    {
        return $this->transaction(function () use ($data): Product {
            $variants = $data['variants'] ?? [];
            unset($data['variants']);

            $data['sku'] = ($data['sku'] ?? null) ?: $this->generateSku($data['name']);

            /** @var Product $product */
            $product = $this->products->create($data);

            $this->syncVariants($product, $variants);

            return $product->load(['category', 'variants']);
        });
    }

    /** @param array<string,mixed> $data */
    public function update(Product $product, array $data): Product
    {
        return $this->transaction(function () use ($product, $data): Product {
            $variants = $data['variants'] ?? null;
            unset($data['variants']);

            $this->products->update($product, $data);

            if ($variants !== null) {
                $this->syncVariants($product, $variants);
            }

            return $product->load(['category', 'variants']);
        });
    }

    public function delete(Product $product): bool
    {
        return $this->products->delete($product);
    }

    /** @param array<int,array<string,mixed>> $variants */
    private function syncVariants(Product $product, array $variants): void
    {
        $keepIds = [];

        foreach ($variants as $variant) {
            $variant['sku'] = $variant['sku'] ?? $this->generateSku($product->name.'-'.($variant['name'] ?? 'v'));

            if (! empty($variant['id'])) {
                $existing = $product->variants()->find($variant['id']);
                if ($existing) {
                    $existing->update($variant);
                    $keepIds[] = $existing->id;

                    continue;
                }
            }

            $created = $product->variants()->create($variant);
            $keepIds[] = $created->id;
        }

        // Remove variants no longer present.
        $product->variants()->whereNotIn('id', $keepIds)->delete();
    }

    private function generateSku(string $name): string
    {
        $base = strtoupper(Str::slug(Str::limit($name, 12, ''), ''));
        $base = $base !== '' ? substr($base, 0, 6) : 'PRD';

        return $base.'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}

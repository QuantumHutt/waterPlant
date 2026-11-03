<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'unit' => $this->unit->value,
            'unit_type' => $this->unit_type,
            'pack_size' => $this->pack_size,
            'pack_of_product_id' => $this->pack_of_product_id,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'sale_price' => (float) $this->sale_price,
            'wholesale_price' => $this->wholesale_price !== null ? (float) $this->wholesale_price : null,
            'reorder_level' => (float) $this->reorder_level,
            'opening_stock' => (float) $this->opening_stock,
            'on_hand' => $this->when(isset($this->on_hand), fn () => (float) $this->on_hand),
            'track_inventory' => $this->track_inventory,
            'is_active' => $this->is_active,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

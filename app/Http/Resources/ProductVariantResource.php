<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class ProductVariantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'capacity_ml' => $this->capacity_ml,
            'cost_price' => (float) $this->cost_price,
            'sale_price' => (float) $this->sale_price,
            'reorder_level' => (float) $this->reorder_level,
            'is_active' => $this->is_active,
        ];
    }
}

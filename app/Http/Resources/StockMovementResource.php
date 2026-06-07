<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockMovement */
class StockMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'product' => $this->whenLoaded('product', fn () => $this->product->name),
            'variant' => $this->whenLoaded('variant', fn () => $this->variant?->name),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'balance_after' => (float) $this->balance_after,
            'note' => $this->note,
            'moved_at' => $this->moved_at?->toDateTimeString(),
        ];
    }
}

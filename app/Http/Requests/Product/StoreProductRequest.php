<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Support\Enums\ProductType;
use App\Support\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:product_categories,id'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(ProductType::values())],
            'unit' => ['required', Rule::in(ProductUnit::values())],
            'pack_size' => ['nullable', 'integer', 'min:1'],
            'unit_type' => ['nullable', Rule::in(['single', 'pack'])],
            'pack_of_product_id' => ['nullable', 'exists:products,id', 'required_if:unit_type,pack'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'track_inventory' => ['boolean'],
            'is_active' => ['boolean'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:120'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.capacity_ml' => ['nullable', 'integer', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.reorder_level' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'operator_id' => ['nullable', 'exists:users,id'],
            'production_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'raw_water_litres' => ['nullable', 'numeric', 'min:0'],
            'filtered_water_litres' => ['nullable', 'numeric', 'min:0'],
            'wastage_litres' => ['nullable', 'numeric', 'min:0'],
            'bottles_produced' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'outputs' => ['nullable', 'array'],
            'outputs.*.product_id' => ['required', 'exists:products,id'],
            'outputs.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'outputs.*.quantity' => ['required', 'numeric', 'gt:0'],
            'consumptions' => ['nullable', 'array'],
            'consumptions.*.product_id' => ['required', 'exists:products,id'],
            'consumptions.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'consumptions.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $litres = (float) $this->input('filtered_water_litres', 0);
            $hasOutputs = is_array($this->input('outputs')) && count($this->input('outputs')) > 0;
            if ($litres <= 0 && ! $hasOutputs) {
                $v->errors()->add('filtered_water_litres', 'Enter produced water litres or add at least one finished output.');
            }
        });
    }
}

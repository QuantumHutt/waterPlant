<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company' => ['sometimes', 'array'],
            'company.name' => ['sometimes', 'string', 'max:255'],
            'company.legal_name' => ['nullable', 'string', 'max:255'],
            'company.phone' => ['nullable', 'string', 'max:30'],
            'company.email' => ['nullable', 'email', 'max:255'],
            'company.address' => ['nullable', 'string', 'max:500'],
            'company.ntn' => ['nullable', 'string', 'max:50'],

            'currency' => ['sometimes', 'array'],
            'currency.code' => ['sometimes', 'string', 'max:5'],
            'currency.symbol' => ['sometimes', 'string', 'max:5'],
            'currency.position' => ['sometimes', 'in:before,after'],
            'currency.decimals' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'currency.abbreviation' => ['sometimes', 'boolean'],

            'tax' => ['sometimes', 'array'],
            'tax.enabled' => ['sometimes', 'boolean'],
            'tax.default_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax.inclusive' => ['sometimes', 'boolean'],

            'invoice' => ['sometimes', 'array'],
            'invoice.prefix' => ['sometimes', 'string', 'max:10'],
            'invoice.next_number' => ['sometimes', 'integer', 'min:1'],
            'invoice.terms' => ['nullable', 'string', 'max:1000'],
            'invoice.footer' => ['nullable', 'string', 'max:500'],
            'invoice.show_logo' => ['sometimes', 'boolean'],
        ];
    }
}

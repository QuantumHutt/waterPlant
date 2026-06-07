<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CustomerService extends BaseService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @param array<string,mixed> $filters */
    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Customer::query()
            ->when(! empty($filters['search']), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('code', 'like', "%{$filters['search']}%")
                ->orWhere('phone', 'like', "%{$filters['search']}%")))
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): Customer
    {
        return $this->transaction(function () use ($data): Customer {
            $data['code'] = $data['code'] ?? 'CUST-'.str_pad((string) (Customer::max('id') + 1), 5, '0', STR_PAD_LEFT);
            $data['current_balance'] = $data['opening_balance'] ?? 0;

            $customer = Customer::create($data);

            if ((float) $customer->opening_balance !== 0.0) {
                $this->ledger->post($customer, (float) $customer->opening_balance, 0, $customer, 'Opening balance');
            }

            return $customer;
        });
    }

    /** @param array<string,mixed> $data */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    public function delete(Customer $customer): bool
    {
        return (bool) $customer->delete();
    }

    /** Record a payment received from the customer (reduces receivable). */
    public function recordPayment(Customer $customer, float $amount, ?string $note = null): void
    {
        $this->ledger->post($customer, 0, $amount, null, $note ?? 'Payment received');
    }
}

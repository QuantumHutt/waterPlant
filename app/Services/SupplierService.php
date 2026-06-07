<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService extends BaseService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @param array<string,mixed> $filters */
    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Supplier::query()
            ->when(! empty($filters['search']), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('code', 'like', "%{$filters['search']}%")))
            ->latest()->paginate($perPage)->withQueryString();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): Supplier
    {
        return $this->transaction(function () use ($data): Supplier {
            $data['code'] = $data['code'] ?? 'SUP-'.str_pad((string) (Supplier::max('id') + 1), 5, '0', STR_PAD_LEFT);
            $data['current_balance'] = $data['opening_balance'] ?? 0;
            $supplier = Supplier::create($data);

            if ((float) $supplier->opening_balance !== 0.0) {
                $this->ledger->post($supplier, (float) $supplier->opening_balance, 0, $supplier, 'Opening balance');
            }

            return $supplier;
        });
    }

    /** @param array<string,mixed> $data */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier;
    }

    public function delete(Supplier $supplier): bool
    {
        return (bool) $supplier->delete();
    }

    /** Record a payment made to the supplier (reduces payable). */
    public function recordPayment(Supplier $supplier, float $amount, ?string $note = null): void
    {
        $this->ledger->post($supplier, 0, $amount, null, $note ?? 'Payment made');
    }
}

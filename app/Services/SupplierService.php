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

    /** Pay a supplier: money out of the drawer / bank (cash book) + reduces what we owe them. */
    public function recordPayment(Supplier $supplier, float $amount, ?string $note = null, string $method = 'cash'): void
    {
        $this->transaction(function () use ($supplier, $amount, $note, $method): void {
            \App\Models\Payment::create([
                'reference' => 'PAY-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5)),
                'payable_type' => $supplier->getMorphClass(),
                'payable_id' => $supplier->getKey(),
                'amount' => round($amount, 2),
                'method' => $method,
                'direction' => 'out',
                'paid_at' => now()->toDateString(),
                'note' => $note,
                'created_by' => auth()->id(),
            ]);
            $this->ledger->post($supplier, 0, round($amount, 2), null, $note ?? 'Payment made');
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerGallonBalance;
use App\Models\GallonTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GallonService extends BaseService
{
    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return GallonTransaction::with('customer')->latest()->paginate($perPage);
    }

    /**
     * Record a gallon transaction and update the customer's running balance.
     *
     * @param array<string,mixed> $data
     */
    public function record(array $data): GallonTransaction
    {
        return $this->transaction(function () use ($data): GallonTransaction {
            $tx = GallonTransaction::create([
                'customer_id' => $data['customer_id'],
                'type' => $data['type'],
                'quantity' => (int) ($data['quantity'] ?? 0),
                'deposit_amount' => (float) ($data['deposit_amount'] ?? 0),
                'note' => $data['note'] ?? null,
                'transacted_at' => $data['transacted_at'] ?? now()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            $balance = CustomerGallonBalance::firstOrCreate(['customer_id' => $data['customer_id']]);

            match ($tx->type) {
                'issue' => $balance->held_qty += $tx->quantity,
                'return' => $balance->held_qty -= $tx->quantity,
                'missing' => [$balance->held_qty -= $tx->quantity, $balance->missing_qty += $tx->quantity],
                'damaged' => [$balance->held_qty -= $tx->quantity, $balance->damaged_qty += $tx->quantity],
                default => null,
            };

            if ($tx->type === 'deposit') {
                $balance->deposit_held += (float) $tx->deposit_amount;
            } elseif ($tx->type === 'deposit_refund') {
                $balance->deposit_held -= (float) $tx->deposit_amount;
            }

            $balance->held_qty = max(0, $balance->held_qty);
            $balance->save();

            return $tx;
        });
    }
}

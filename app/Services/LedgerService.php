<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Party (customer/supplier) running-balance ledger.
 *
 * Convention (uniform for both party types): debit increases the outstanding
 * balance, credit decreases it. So `party.current_balance` always reads as the
 * amount currently outstanding for that party.
 */
class LedgerService extends BaseService
{
    public function post(
        Model $party,
        float $debit,
        float $credit,
        ?Model $reference = null,
        ?string $narration = null,
        ?string $date = null,
    ): LedgerEntry {
        return $this->transaction(function () use ($party, $debit, $credit, $reference, $narration, $date): LedgerEntry {
            $balance = round((float) $party->current_balance + $debit - $credit, 2);

            $entry = LedgerEntry::create([
                'party_type' => $party->getMorphClass(),
                'party_id' => $party->getKey(),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'narration' => $narration,
                'entry_date' => $date ?? now()->toDateString(),
            ]);

            $party->forceFill(['current_balance' => $balance])->save();

            return $entry;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry engine. The only way journal entries are created.
 * Every entry must balance to the paisa or it is rejected.
 */
class AccountingService extends BaseService
{
    /**
     * Post a balanced journal entry.
     *
     * @param array<int,array{account_id:int, debit?:float, credit?:float, narration?:string}> $lines
     *
     * @throws UnbalancedJournalException
     */
    public function post(array $lines, ?string $narration = null, ?Model $reference = null, ?string $date = null): JournalEntry
    {
        // Compare in integer paisa to avoid float drift — catches a Rs 1 difference.
        $debitPaisa = 0;
        $creditPaisa = 0;
        foreach ($lines as $line) {
            $debitPaisa += Money::toPaisa($line['debit'] ?? 0);
            $creditPaisa += Money::toPaisa($line['credit'] ?? 0);
        }

        if ($debitPaisa !== $creditPaisa) {
            throw UnbalancedJournalException::make(Money::toRupees($debitPaisa), Money::toRupees($creditPaisa));
        }

        if ($debitPaisa === 0) {
            throw new \InvalidArgumentException('A journal entry must move a non-zero amount.');
        }

        return $this->transaction(function () use ($lines, $narration, $reference, $date): JournalEntry {
            $entry = JournalEntry::create([
                'entry_number' => 'JE-'.now()->format('ymd').'-'.str_pad((string) (JournalEntry::max('id') + 1), 5, '0', STR_PAD_LEFT),
                'entry_date' => $date ?? now()->toDateString(),
                'narration' => $narration,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'is_posted' => true,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'narration' => $line['narration'] ?? $narration,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /** Convenience: post a simple two-line entry by account code. */
    public function simpleEntry(string $debitCode, string $creditCode, float $amount, ?string $narration = null, ?Model $reference = null, ?string $date = null): JournalEntry
    {
        return $this->post([
            ['account_id' => Account::byCode($debitCode)?->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => Account::byCode($creditCode)?->id, 'debit' => 0, 'credit' => $amount],
        ], $narration, $reference, $date);
    }

    /**
     * Trial balance: per-account net debit/credit. The grand totals must be equal.
     *
     * @return array{rows: array<int,array<string,mixed>>, total_debit: float, total_credit: float, balanced: bool}
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $q = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->when($from, fn ($x) => $x->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($x) => $x->where('journal_entries.entry_date', '<=', $to))
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type')
            ->selectRaw('chart_of_accounts.code, chart_of_accounts.name, chart_of_accounts.type,
                SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($q as $r) {
            $net = round((float) $r->debit - (float) $r->credit, 2);
            $debit = $net >= 0 ? $net : 0;
            $credit = $net < 0 ? -$net : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;
            $rows[] = ['code' => $r->code, 'name' => $r->name, 'type' => $r->type, 'debit' => $debit, 'credit' => $credit];
        }

        return [
            'rows' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'balanced' => Money::toPaisa($totalDebit) === Money::toPaisa($totalCredit),
        ];
    }

    /** @return array<string,mixed> */
    public function profitAndLoss(?string $from = null, ?string $to = null): array
    {
        $income = $this->totalsByType('income', $from, $to);
        $expense = $this->totalsByType('expense', $from, $to);
        $incomeTotal = array_sum(array_column($income, 'amount'));
        $expenseTotal = array_sum(array_column($expense, 'amount'));

        return [
            'income' => $income,
            'expense' => $expense,
            'income_total' => round($incomeTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'net_profit' => round($incomeTotal - $expenseTotal, 2),
        ];
    }

    /** @return array<string,mixed> */
    public function balanceSheet(?string $to = null): array
    {
        $assets = $this->totalsByType('asset', null, $to);
        $liabilities = $this->totalsByType('liability', null, $to);
        $equity = $this->totalsByType('equity', null, $to);
        $pl = $this->profitAndLoss(null, $to);

        $assetTotal = array_sum(array_column($assets, 'amount'));
        $liabilityTotal = array_sum(array_column($liabilities, 'amount'));
        $equityTotal = array_sum(array_column($equity, 'amount')) + $pl['net_profit'];

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'asset_total' => round($assetTotal, 2),
            'liability_total' => round($liabilityTotal, 2),
            'equity_total' => round($equityTotal, 2),
            'retained_earnings' => $pl['net_profit'],
            'balanced' => Money::toPaisa($assetTotal) === Money::toPaisa($liabilityTotal + $equityTotal),
        ];
    }

    /**
     * Net amount per account for a given type. Income/liability/equity are
     * credit-normal (credit - debit); asset/expense are debit-normal.
     *
     * @return array<int,array{code:string,name:string,amount:float}>
     */
    private function totalsByType(string $type, ?string $from, ?string $to): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->where('chart_of_accounts.type', $type)
            ->when($from, fn ($x) => $x->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($x) => $x->where('journal_entries.entry_date', '<=', $to))
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name')
            ->selectRaw('chart_of_accounts.code, chart_of_accounts.name,
                SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->get();

        $creditNormal = in_array($type, ['income', 'liability', 'equity'], true);

        return $rows->map(function ($r) use ($creditNormal) {
            $amount = $creditNormal
                ? (float) $r->credit - (float) $r->debit
                : (float) $r->debit - (float) $r->credit;

            return ['code' => $r->code, 'name' => $r->name, 'amount' => round($amount, 2)];
        })->filter(fn ($a) => abs($a['amount']) > 0.001)->values()->all();
    }
}

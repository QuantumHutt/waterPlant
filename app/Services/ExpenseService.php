<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ExpenseService extends BaseService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'creator:id,name'])->latest('spent_at')->latest('id')->paginate($perPage);
    }

    /**
     * Record an expense and post the matching journal entry:
     *   Dr  Expense account     (increase expense)
     *       Cr  Cash / Bank     (decrease asset)
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): Expense
    {
        return $this->transaction(function () use ($data): Expense {
            $expense = Expense::create([
                'reference' => 'EXP-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'category_id' => $data['category_id'],
                'amount' => $data['amount'],
                'paid_via' => $data['paid_via'] ?? 'cash',
                'vendor' => $data['vendor'] ?? null,
                'spent_at' => $data['spent_at'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->postJournal($expense);

            return $expense->load('category');
        });
    }

    /**
     * Fix a wrong expense (e.g. 500 typed instead of 50). The old journal is reversed and the
     * corrected one posted; the change itself is kept in the audit log (who, when, old → new).
     *
     * @param array<string,mixed> $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        return $this->transaction(function () use ($expense, $data): Expense {
            $this->reverseJournal($expense, "Correction of {$expense->reference}");
            $expense->update([
                'category_id' => $data['category_id'],
                'amount' => $data['amount'],
                'paid_via' => $data['paid_via'] ?? $expense->paid_via,
                'vendor' => $data['vendor'] ?? $expense->vendor,
                'spent_at' => $data['spent_at'] ?? $expense->spent_at,
                'note' => $data['note'] ?? $expense->note,
            ]);
            $this->postJournal($expense->fresh());

            return $expense->load('category');
        });
    }

    /** Remove an expense entered by mistake: journal reversed, record deleted (kept in audit log). */
    public function delete(Expense $expense): void
    {
        $this->transaction(function () use ($expense): void {
            $this->reverseJournal($expense, "Deleted {$expense->reference}");
            $expense->delete();
        });
    }

    /** Dr expense account / Cr cash or bank. */
    private function postJournal(Expense $expense): void
    {
        $category = ExpenseCategory::find($expense->category_id);
        $this->accounting->post([
            ['account_id' => $category?->account_id ?? Account::byCode('5900')?->id, 'debit' => (float) $expense->amount, 'credit' => 0],
            ['account_id' => Account::byCode($expense->paid_via === 'bank' ? '1010' : '1000')?->id, 'debit' => 0, 'credit' => (float) $expense->amount],
        ], "Expense {$expense->reference} — {$category?->name}", $expense, $expense->spent_at->toDateString());
    }

    /** Post the exact opposite of every journal line recorded so far for this expense. */
    private function reverseJournal(Expense $expense, string $narration): void
    {
        $lines = \App\Models\JournalLine::whereHas('entry', fn ($q) => $q
            ->where('reference_type', $expense->getMorphClass())->where('reference_id', $expense->getKey()))
            ->get(['account_id', 'debit', 'credit']);

        $net = $lines->groupBy('account_id')->map(fn ($g) => (float) $g->sum('debit') - (float) $g->sum('credit'))
            ->filter(fn ($v) => round($v, 2) != 0.0);
        if ($net->isEmpty()) {
            return;
        }
        $this->accounting->post($net->map(fn ($v, $accountId) => [
            'account_id' => $accountId, 'debit' => $v < 0 ? -$v : 0, 'credit' => $v > 0 ? $v : 0,
        ])->values()->all(), $narration, $expense, now()->toDateString());
    }
}

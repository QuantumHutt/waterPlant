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
        return Expense::with('category')->latest('spent_at')->paginate($perPage);
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

            $category = ExpenseCategory::find($expense->category_id);
            $expenseAccount = $category?->account_id ?? Account::byCode('5900')?->id;
            $cashAccount = Account::byCode($expense->paid_via === 'bank' ? '1010' : '1000')?->id;

            $this->accounting->post([
                ['account_id' => $expenseAccount, 'debit' => (float) $expense->amount, 'credit' => 0],
                ['account_id' => $cashAccount, 'debit' => 0, 'credit' => (float) $expense->amount],
            ], "Expense {$expense->reference} — {$category?->name}", $expense, $expense->spent_at->toDateString());

            return $expense->load('category');
        });
    }
}

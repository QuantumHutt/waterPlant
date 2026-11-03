<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashClosing;
use App\Models\CashTransfer;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cash book: every rupee in or out of the CASH DRAWER and the BANK, built from source records.
 *
 *  In   cash sales · udhaar received · advances · bank withdrawals
 *  Out  expenses · supplier payments · refunds · bank deposits
 *
 * Payment method cash → drawer; bank / card / cheque → bank. Method "advance" is skipped
 * (that money was already counted the day the advance came in).
 */
class CashBookService extends BaseService
{
    public const KINDS = [
        'sale' => 'Cash sales',
        'udhaar' => 'Udhaar received',
        'advance' => 'Advance received',
        'withdrawal' => 'Bank → cash',
        'expense' => 'Expenses',
        'supplier' => 'Supplier payments',
        'refund' => 'Refunds (returns)',
        'deposit' => 'Cash → bank deposit',
    ];

    /** @return 'cash'|'bank'|null */
    public static function accountFor(string $method): ?string
    {
        return match ($method) {
            'cash' => 'cash',
            'bank', 'card', 'cheque' => 'bank',
            default => null,   // advance / credit: no money moved
        };
    }

    /**
     * Full picture for one day: opening, money in/out by type, expected closing — cash and bank.
     *
     * @return array<string, mixed>
     */
    public function day(string $date): array
    {
        $moves = $this->movements($date, $date);
        $out = [];
        foreach (['cash', 'bank'] as $account) {
            $opening = $this->balance($account, $date);
            $order = array_flip(array_keys(self::KINDS));
            $lines = $moves->where('account', $account)
                ->groupBy(fn ($m) => $m['kind'].'|'.$m['direction'])
                ->map(fn ($g) => [
                    'kind' => $g[0]['kind'],
                    'direction' => $g[0]['direction'],
                    // the bank side of a transfer reads from the bank's point of view
                    'label' => $account === 'bank' && $g[0]['kind'] === 'deposit' ? 'Deposited from cash'
                        : ($account === 'bank' && $g[0]['kind'] === 'withdrawal' ? 'Withdrawn to cash' : $g[0]['label']),
                    'amount' => round((float) $g->sum('amount'), 2),
                ])
                ->sortBy(fn ($l) => [$l['direction'] === 'in' ? 0 : 1, $order[$l['kind']]])
                ->values();
            $in = round((float) $lines->where('direction', 'in')->sum('amount'), 2);
            $outTotal = round((float) $lines->where('direction', 'out')->sum('amount'), 2);
            $out[$account] = [
                'opening' => $opening,
                'lines' => $lines->values(),
                'in' => $in,
                'out' => $outTotal,
                'closing' => round($opening + $in - $outTotal, 2),
            ];
        }

        return $out + [
            'date' => $date,
            'entries' => $moves->sortBy('time')->values(),
            'closing' => CashClosing::with('closer:id,name')->whereDate('closing_date', $date)->first(),
        ];
    }

    /** Balance of an account at the START of $date (everything before it + opening setting). */
    public function balance(string $account, string $date): float
    {
        $opening = (float) (Setting::get('cash', $account === 'cash' ? 'opening_cash' : 'opening_bank') ?? 0);
        $moves = $this->movements('1900-01-01', date('Y-m-d', strtotime($date.' -1 day')))->where('account', $account);

        return round($opening + (float) $moves->sum(fn ($m) => $m['signed']), 2);
    }

    /** Move money between drawer and bank (e.g. deposit today's cash). */
    public function transfer(string $from, string $to, float $amount, ?string $date = null, ?string $note = null): CashTransfer
    {
        return CashTransfer::create([
            'reference' => ($to === 'bank' ? 'DEP-' : 'WDR-').now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'from_account' => $from,
            'to_account' => $to,
            'amount' => round($amount, 2),
            'transfer_date' => $date ?? now()->toDateString(),
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }

    /** Save the end-of-day count. Re-closing the same day updates it. */
    public function close(string $date, float $counted, ?string $note = null): CashClosing
    {
        $day = $this->day($date);

        return CashClosing::updateOrCreate(['closing_date' => $date], [
            'expected_cash' => $day['cash']['closing'],
            'counted_cash' => round($counted, 2),
            'difference' => round($counted - $day['cash']['closing'], 2),
            'bank_balance' => $day['bank']['closing'],
            'note' => $note,
            'closed_by' => auth()->id(),
        ]);
    }

    /**
     * Every money movement in the range, one row per account side.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function movements(string $from, string $to): Collection
    {
        $rows = collect();

        // Payments (sales, udhaar, advances, refunds, supplier payments).
        $payments = DB::table('payments')
            ->leftJoin('invoices', function ($j) {
                $j->on('invoices.id', '=', 'payments.payable_id')->where('payments.payable_type', '=', (new \App\Models\Invoice)->getMorphClass());
            })
            ->whereBetween('payments.paid_at', [$from, $to])
            ->whereNotIn('payments.method', ['advance', 'credit'])
            ->get(['payments.*', 'invoices.invoice_date', 'invoices.invoice_number']);

        $invoiceType = (new \App\Models\Invoice)->getMorphClass();
        $customerType = (new \App\Models\Customer)->getMorphClass();
        foreach ($payments as $p) {
            $account = self::accountFor($p->method);
            if (! $account) {
                continue;
            }
            $kind = match (true) {
                $p->direction === 'out' && $p->payable_type === $invoiceType => 'refund',
                $p->direction === 'out' => 'supplier',
                $p->payable_type === $customerType && str_starts_with($p->reference, 'ADV-') => 'advance',
                $p->payable_type === $customerType => 'udhaar',
                $p->payable_type === $invoiceType && $p->invoice_date && substr((string) $p->invoice_date, 0, 10) < substr((string) $p->paid_at, 0, 10) => 'udhaar',
                default => 'sale',
            };
            $rows->push($this->row($account, $kind, (float) $p->amount, $p->direction === 'in', $p->paid_at, $p->created_at, $p->reference, $p->invoice_number ?? $p->note));
        }

        // Expenses.
        foreach (DB::table('expenses')->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->whereBetween('expenses.spent_at', [$from, $to])
            ->get(['expenses.*', 'expense_categories.name as category']) as $e) {
            $rows->push($this->row($e->paid_via === 'bank' ? 'bank' : 'cash', 'expense', (float) $e->amount, false, $e->spent_at, $e->created_at, $e->reference, trim($e->category.' '.($e->note ?? ''))));
        }

        // Drawer ↔ bank transfers (one row on each side).
        foreach (DB::table('cash_transfers')->whereBetween('transfer_date', [$from, $to])->get() as $t) {
            $kind = $t->to_account === 'bank' ? 'deposit' : 'withdrawal';
            $rows->push($this->row($t->from_account, $kind, (float) $t->amount, false, $t->transfer_date, $t->created_at, $t->reference, $t->note));
            $rows->push($this->row($t->to_account, $kind, (float) $t->amount, true, $t->transfer_date, $t->created_at, $t->reference, $t->note));
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(string $account, string $kind, float $amount, bool $in, mixed $date, mixed $time, string $ref, ?string $note): array
    {
        return [
            'account' => $account,
            'kind' => $kind,
            'label' => self::KINDS[$kind],
            'amount' => round($amount, 2),
            'signed' => round($in ? $amount : -$amount, 2),
            'direction' => $in ? 'in' : 'out',
            'date' => substr((string) $date, 0, 10),
            'time' => (string) $time,
            'reference' => $ref,
            'note' => $note,
        ];
    }
}

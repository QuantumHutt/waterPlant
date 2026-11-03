<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for udhaar recorded before payments were unified:
 *  1. Invoice payments that never reached the customer ledger → post the missing ledger credit.
 *  2. Ledger payments that never reached the invoices → mark oldest invoices paid (no new cash,
 *     no new ledger entry — the money was already counted on the ledger).
 *
 * Dry run by default; pass --apply to write.
 */
class ReconcileUdhaar extends Command
{
    protected $signature = 'udhaar:reconcile {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Bring customer udhaar balances and invoice balances back in step';

    public function handle(LedgerService $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Applying changes…' : 'Dry run — nothing will be written. Use --apply to save.');
        $changes = 0;

        DB::transaction(function () use ($ledger, $apply, &$changes): void {
            foreach (Customer::whereHas('invoices')->get() as $customer) {
                // 1. Invoice payments missing from the ledger.
                foreach (Invoice::where('customer_id', $customer->id)->get() as $invoice) {
                    $paid = (float) Payment::where('payable_type', $invoice->getMorphClass())->where('payable_id', $invoice->id)->sum('amount');
                    $credited = (float) LedgerEntry::where('party_type', $customer->getMorphClass())->where('party_id', $customer->id)
                        ->where('reference_type', $invoice->getMorphClass())->where('reference_id', $invoice->id)->sum('credit');
                    $missing = round($paid - $credited, 2);
                    if ($missing > 0) {
                        $changes++;
                        $this->line("  {$customer->name}: ledger credit {$missing} for {$invoice->invoice_number} (paid on invoice, not on ledger)");
                        if ($apply) {
                            $ledger->post($customer->fresh(), 0, $missing, $invoice, "Reconcile: payment on {$invoice->invoice_number}");
                        }
                    }
                }

                // 2. Ledger payments missing from the invoices.
                $customer->refresh();
                $open = Invoice::where('customer_id', $customer->id)->where('balance', '>', 0)
                    ->whereNotIn('status', ['cancelled', 'returned'])->orderBy('invoice_date')->orderBy('id')->get();
                $excess = round((float) $open->sum('balance') - max(0.0, (float) $customer->current_balance), 2);

                foreach ($open as $invoice) {
                    if ($excess <= 0) {
                        break;
                    }
                    $settle = min($excess, (float) $invoice->balance);
                    $excess = round($excess - $settle, 2);
                    $changes++;
                    $this->line("  {$customer->name}: mark {$settle} paid on {$invoice->invoice_number} (collected on ledger earlier)");
                    if ($apply) {
                        $paidTotal = round((float) $invoice->paid_total + $settle, 2);
                        $balance = round((float) $invoice->grand_total - $paidTotal, 2);
                        $invoice->update([
                            'paid_total' => $paidTotal,
                            'balance' => $balance,
                            'status' => $balance <= 0 ? 'paid' : 'partial',
                        ]);
                    }
                }
            }
        });

        $this->info($changes ? "{$changes} change(s) ".($apply ? 'applied.' : 'found.') : 'Everything already matches.');

        return self::SUCCESS;
    }
}

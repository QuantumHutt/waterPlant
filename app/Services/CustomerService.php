<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CustomerService extends BaseService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SaleService $sales,
    ) {}

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
    /**
     * Money taken in advance (customer has no udhaar, or pays ahead on purpose). Recorded as cash
     * in today and credited to the customer, so their balance goes negative = advance held.
     * It is used up automatically on their next bills.
     */
    public function receiveAdvance(Customer $customer, float $amount, string $method = 'cash', ?string $note = null): void
    {
        $this->transaction(function () use ($customer, $amount, $method, $note): void {
            Payment::create([
                'reference' => 'ADV-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'payable_type' => $customer->getMorphClass(),
                'payable_id' => $customer->getKey(),
                'amount' => round($amount, 2),
                'method' => $method,
                'direction' => 'in',
                'paid_at' => now()->toDateString(),
                'note' => $note ?? 'Advance received',
                'created_by' => auth()->id(),
            ]);
            $this->ledger->post($customer->fresh(), 0, round($amount, 2), null, $note ?? 'Advance received');
        });
    }

    /**
     * Udhaar collected from a customer. Settles their unpaid invoices oldest-first (each invoice's
     * balance, status, payment record and ledger move together); anything left over — e.g. an
     * opening balance with no invoice — is credited to their account.
     *
     * @return array{applied: float, on_account: float, invoices: list<string>}
     */
    public function recordPayment(Customer $customer, float $amount, ?string $note = null, string $method = 'cash'): array
    {
        return $this->transaction(function () use ($customer, $amount, $note, $method): array {
            $remaining = round($amount, 2);
            $settled = [];

            $open = Invoice::where('customer_id', $customer->id)
                ->where('balance', '>', 0)
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->orderBy('invoice_date')->orderBy('id')
                ->lockForUpdate()->get();

            foreach ($open as $invoice) {
                if ($remaining <= 0) {
                    break;
                }
                $pay = min($remaining, (float) $invoice->balance);
                $this->sales->recordPayment($invoice, $pay, $method);
                $remaining = round($remaining - $pay, 2);
                $settled[] = $invoice->invoice_number;
            }

            if ($remaining > 0) {
                Payment::create([
                    'reference' => 'RCV-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                    'payable_type' => $customer->getMorphClass(),
                    'payable_id' => $customer->getKey(),
                    'amount' => $remaining,
                    'method' => $method,
                    'direction' => 'in',
                    'paid_at' => now()->toDateString(),
                    'note' => $note,
                    'created_by' => auth()->id(),
                ]);
                $this->ledger->post($customer->fresh(), 0, $remaining, null, $note ?? 'Payment received (on account)');
            }

            return ['applied' => round($amount - $remaining, 2), 'on_account' => $remaining, 'invoices' => $settled];
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Support\Enums\StockMovementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService extends BaseService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly GallonService $gallons,
    ) {}

    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Invoice::with('customer')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Create an invoice, decrement stock, and settle payment / receivable.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): Invoice
    {
        return $this->transaction(function () use ($data): Invoice {
            $items = $data['items'];
            $subtotal = 0.0;
            $taxTotal = 0.0;
            foreach ($items as &$line) {
                $gross = (float) $line['quantity'] * (float) $line['unit_price'];
                // Manual line discount (owner "sasta dena") — can't exceed the line value.
                $line['discount'] = min((float) ($line['discount'] ?? 0), $gross);
                $line['tax'] = (float) ($line['tax'] ?? 0);
                $line['line_total'] = round($gross - $line['discount'] + $line['tax'], 2);
                $subtotal += $gross - $line['discount'];
                $taxTotal += $line['tax'];
            }
            unset($line);

            $discount = min((float) ($data['discount'] ?? 0), $subtotal); // never a negative bill
            $grand = round($subtotal - $discount + $taxTotal, 2);
            $paid = (float) ($data['paid_total'] ?? ($data['type'] === 'credit' ? 0 : $grand));
            $paid = min($paid, $grand);

            $invoice = Invoice::create([
                'invoice_number' => $this->invoiceNumber(),
                'customer_id' => $data['customer_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'salesman_id' => auth()->id(),
                'type' => $data['type'] ?? 'pos',
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'tax_total' => round($taxTotal, 2),
                'grand_total' => $grand,
                // Start unpaid; the payment below applies the amount exactly once.
                'paid_total' => 0,
                'balance' => $grand,
                'status' => 'unpaid',
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $line) {
                // Snapshot today's cost so later price edits never rewrite historical profit.
                // A product with no configured cost (e.g. 19L refill) stores NULL → profit pending.
                $cost = Product::whereKey($line['product_id'])->value('cost_price');
                $line['unit_cost'] = $cost !== null ? (float) $cost : null;
                $invoice->items()->create($line);
                $this->deductForLine($invoice, $line);
            }

            if (array_key_exists('bottles_returned', $data) && $data['bottles_returned'] !== null) {
                $this->trackBottleExchange($invoice, $items, (int) $data['bottles_returned']);
            }

            // New 19L bottle sale: deduct a company bottle + record the refundable deposit.
            if (! empty($data['new_bottle'])) {
                $this->issueBottleWithDeposit($invoice, (float) ($data['deposit'] ?? Setting::get('sales', 'bottle_deposit') ?? 0));
            }

            if ($invoice->customer_id) {
                $customer = Customer::find($invoice->customer_id);
                // Receivable created for the full invoice; recordPayment() below credits what was paid.
                $this->ledger->post($customer, $grand, 0, $invoice, "Invoice {$invoice->invoice_number}");
            }

            if ($paid > 0) {
                $this->recordPayment($invoice, $paid, $data['payment_method'] ?? 'cash');
            }

            return $invoice->load('items');
        });
    }

    /**
     * Deduct stock for a single invoice line per the business rules:
     *  - tracked, non-service product  → reduce that product's finished-goods stock
     *  - product with water_litres > 0 → reduce bulk filtered water (refills / new bottle)
     *
     * @param array<string,mixed> $line
     */
    private function deductForLine(Invoice $invoice, array $line): void
    {
        $product = Product::find($line['product_id']);
        if (! $product) {
            return;
        }
        $qty = (float) $line['quantity'];

        // A pack/"pat" deducts pack_size base bottles from the single product's stock.
        if ($product->isPack()) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $product->pack_of_product_id,
                'type' => StockMovementType::Sale,
                'quantity' => $qty * max(1, (int) $product->pack_size),
                'reference' => $invoice,
                'note' => "Pack ({$product->pack_size}) — Invoice {$invoice->invoice_number}",
            ]);

            return;
        }

        if ($product->track_inventory && ! $product->is_service) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $product->id,
                'variant_id' => $line['variant_id'] ?? null,
                'type' => StockMovementType::Sale,
                'quantity' => $qty,
                'reference' => $invoice,
                'note' => "Invoice {$invoice->invoice_number}",
            ]);
        }

        // Refill water is unlimited unless the owner turns on bulk-water tracking in settings.
        $litres = (float) $product->water_litres * $qty;
        if ($litres > 0 && Setting::get('sales', 'track_refill_water') && ($waterId = $this->waterProductId())) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $waterId,
                'type' => StockMovementType::Sale,
                'quantity' => $litres,
                'reference' => $invoice,
                'note' => "Water for {$invoice->invoice_number}",
            ]);
        }
    }

    /**
     * 19L exchange on a refill sale: each refill hands over a filled company bottle and the
     * customer returns empties. Only the difference moves shop bottle stock, and it is kept
     * on the customer's bottle balance (e.g. 5 delivered, 4 returned → customer owes 1).
     *
     * @param array<int, array<string,mixed>> $items
     */
    private function trackBottleExchange(Invoice $invoice, array $items, int $returned): void
    {
        $refillId = Product::where('sku', Setting::get('sales', 'refill_service_sku') ?? '19L-REFILL')->value('id');
        $delivered = (int) collect($items)->where('product_id', $refillId)->sum('quantity');
        if ($delivered === 0 && $returned === 0) {
            return;
        }

        if (! $invoice->customer_id && $delivered !== $returned) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a customer — bottles given and empties returned don\'t match, so the balance must be tracked.',
            ]);
        }

        $net = $delivered - $returned;
        $bottle = Product::where('sku', Setting::get('sales', 'bottle_19l_sku') ?? '19L-BOTTLE')->first();
        if ($net !== 0 && $bottle && $bottle->track_inventory) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $bottle->id,
                'type' => $net > 0 ? StockMovementType::Out : StockMovementType::In,
                'quantity' => abs($net),
                'reference' => $invoice,
                'note' => "19L exchange {$invoice->invoice_number}: {$delivered} given, {$returned} returned",
            ]);
        }

        if ($invoice->customer_id) {
            if ($delivered > 0) {
                $this->gallons->record(['customer_id' => $invoice->customer_id, 'type' => 'issue', 'quantity' => $delivered, 'note' => $invoice->invoice_number]);
            }
            if ($returned > 0) {
                $this->gallons->record(['customer_id' => $invoice->customer_id, 'type' => 'return', 'quantity' => $returned, 'note' => $invoice->invoice_number]);
            }
        }

        $invoice->update(['bottles_delivered' => $delivered, 'bottles_returned' => $returned]);
    }

    /** Issue a company 19L bottle on a refundable deposit (liability), tracked per customer. */
    private function issueBottleWithDeposit(Invoice $invoice, float $deposit): void
    {
        $bottleSku = Setting::get('sales', 'bottle_19l_sku') ?? '19L-BOTTLE';
        $bottle = Product::where('sku', $bottleSku)->first();

        if ($bottle && $bottle->track_inventory) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $bottle->id,
                'type' => StockMovementType::Out,
                'quantity' => 1,
                'reference' => $invoice,
                'note' => "Bottle issued on deposit ({$invoice->invoice_number})",
            ]);
        }

        // Track the deposit + bottle against the customer (issued bottle + deposit liability).
        if ($invoice->customer_id) {
            $this->gallons->record(['customer_id' => $invoice->customer_id, 'type' => 'issue', 'quantity' => 1, 'note' => $invoice->invoice_number]);
            if ($deposit > 0) {
                $this->gallons->record(['customer_id' => $invoice->customer_id, 'type' => 'deposit', 'deposit_amount' => $deposit, 'note' => $invoice->invoice_number]);
            }
        }

        $invoice->update(['deposit_total' => $deposit]);
    }

    private ?int $waterProductId = null;

    private function waterProductId(): ?int
    {
        if ($this->waterProductId === null) {
            $sku = Setting::get('sales', 'water_product_sku') ?? 'WATER-BULK';
            $this->waterProductId = Product::where('sku', $sku)->value('id');
        }

        return $this->waterProductId;
    }

    /**
     * Receive money against an invoice. Keeps all three in step: the invoice balance/status,
     * a Payment (cash/bank in) record, and the customer's udhaar ledger.
     */
    public function recordPayment(Invoice $invoice, float $amount, string $method = 'cash', ?string $date = null): Payment
    {
        return $this->transaction(function () use ($invoice, $amount, $method, $date): Payment {
            // Never take more than the invoice still owes (extra is handled by CustomerService).
            $amount = round(min($amount, max(0.0, (float) $invoice->balance)), 2);

            $payment = Payment::create([
                'reference' => 'RCV-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'payable_type' => $invoice->getMorphClass(),
                'payable_id' => $invoice->getKey(),
                'amount' => $amount,
                'method' => $method,
                'direction' => 'in',
                'paid_at' => $date ?? now()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            if ($invoice->customer_id && ($customer = Customer::find($invoice->customer_id))) {
                $this->ledger->post($customer, 0, $amount, $invoice, "Payment on {$invoice->invoice_number}", $date);
            }

            $paid = round((float) $invoice->paid_total + $amount, 2);
            $invoice->update([
                'paid_total' => $paid,
                'balance' => round((float) $invoice->grand_total - $paid, 2),
                'status' => $this->status((float) $invoice->grand_total, $paid),
            ]);

            return $payment;
        });
    }

    /** Return a whole invoice: restock items, credit the customer, mark returned. */
    public function returnInvoice(Invoice $invoice, ?string $reason = null): SalesReturn
    {
        return $this->transaction(function () use ($invoice, $reason): SalesReturn {
            $return = SalesReturn::create([
                'return_number' => 'RET-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'total' => $invoice->grand_total,
                'reason' => $reason,
                'returned_at' => now()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            foreach ($invoice->items()->with('product')->get() as $item) {
                $return->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]);

                // Restock what the sale deducted: a pack returns pack_size base bottles;
                // services / untracked products hold no stock of their own.
                $product = $item->product;
                if ($product?->isPack()) {
                    $this->inventory->move([
                        'warehouse_id' => $invoice->warehouse_id,
                        'product_id' => $product->pack_of_product_id,
                        'type' => StockMovementType::Return,
                        'quantity' => (float) $item->quantity * max(1, (int) $product->pack_size),
                        'reference' => $return,
                        'note' => "Pack ({$product->pack_size}) — Return {$return->return_number}",
                    ]);
                } elseif ($product && $product->track_inventory && ! $product->is_service) {
                    $this->inventory->move([
                        'warehouse_id' => $invoice->warehouse_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'type' => StockMovementType::Return,
                        'quantity' => (float) $item->quantity,
                        'reference' => $return,
                        'note' => "Return {$return->return_number}",
                    ]);
                }
            }

            if ($invoice->customer_id) {
                $this->ledger->post(Customer::find($invoice->customer_id), 0, (float) $invoice->grand_total, $return, "Sales return {$return->return_number}");
            }

            $invoice->update(['status' => 'returned']);

            return $return;
        });
    }

    private function status(float $grand, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $grand ? 'paid' : 'partial';
    }

    private function invoiceNumber(): string
    {
        $prefix = \App\Models\Setting::get('invoice', 'prefix') ?? 'INV-';

        return $prefix.now()->format('ymd').'-'.str_pad((string) (Invoice::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}

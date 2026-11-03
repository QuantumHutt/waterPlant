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
        return Invoice::with(['customer', 'items.product:id,name'])
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
                // Advance the customer paid earlier (negative balance) is used on this bill first;
                // only the rest is due in cash.
                $used = round(min(max(0.0, -(float) $customer->current_balance), $grand), 2);
                $paid = min($paid, round($grand - $used, 2));

                // Receivable created for the full invoice; recordPayment() below credits what was paid.
                $this->ledger->post($customer, $grand, 0, $invoice, "Invoice {$invoice->invoice_number}");

                if ($used > 0) {
                    $this->applyAdvance($invoice, $used);
                }
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

    /**
     * Settle part of an invoice from the customer's advance. The cash was already counted (and
     * the ledger credited) when the advance was received, so this only records method "advance"
     * on the invoice — no new cash, no new ledger credit.
     */
    private function applyAdvance(Invoice $invoice, float $amount): void
    {
        Payment::create([
            'reference' => 'ADV-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'payable_type' => $invoice->getMorphClass(),
            'payable_id' => $invoice->getKey(),
            'amount' => $amount,
            'method' => 'advance',
            'direction' => 'in',
            'paid_at' => now()->toDateString(),
            'note' => 'Paid from customer advance',
            'created_by' => auth()->id(),
        ]);

        $paid = round((float) $invoice->paid_total + $amount, 2);
        $invoice->update([
            'paid_total' => $paid,
            'balance' => round((float) $invoice->grand_total - $paid, 2),
            'status' => $this->status((float) $invoice->grand_total, $paid),
        ]);
    }

    /** Return a whole invoice (everything not returned yet). */
    public function returnInvoice(Invoice $invoice, ?string $reason = null, string $refundMethod = 'cash'): SalesReturn
    {
        return $this->returnItems($invoice, $this->allRemaining($invoice), $refundMethod, $reason);
    }

    /** Cancel a wrong bill (owner/manager): full reversal of stock and money, marked "cancelled". */
    public function cancel(Invoice $invoice, string $reason, string $refundMethod = 'cash'): SalesReturn
    {
        return $this->returnItems($invoice, $this->allRemaining($invoice), $refundMethod, 'CANCELLED: '.$reason, cancel: true);
    }

    /**
     * Return some (or all) of a bill.
     *  - stock goes back (packs → base bottles), 19L refills give the filled bottle back
     *  - value of what came back first reduces any udhaar still due on this bill,
     *    the rest is refunded: cash / bank out, or kept as customer advance ("advance")
     *
     * @param array<int|string, float|int> $lines invoice_item_id => quantity to return
     */
    public function returnItems(Invoice $invoice, array $lines, string $refundMethod = 'cash', ?string $reason = null, bool $cancel = false): SalesReturn
    {
        return $this->transaction(function () use ($invoice, $lines, $refundMethod, $reason, $cancel): SalesReturn {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if (in_array($invoice->status, ['returned', 'cancelled'], true)) {
                throw ValidationException::withMessages(['invoice' => "Invoice {$invoice->invoice_number} is already {$invoice->status}."]);
            }
            if ($refundMethod === 'advance' && ! $invoice->customer_id) {
                throw ValidationException::withMessages(['refund_method' => 'Walk-in sale — refund in cash or bank.']);
            }

            $items = $invoice->items()->with('product')->get()->keyBy('id');
            // Bill-level discount/tax spread over the lines: value of a returned unit = line_total/qty × factor.
            $linesTotal = (float) $items->sum('line_total');
            $factor = $linesTotal > 0 ? (float) $invoice->grand_total / $linesTotal : 0.0;

            $return = SalesReturn::create([
                'return_number' => 'RET-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'total' => 0,
                'reason' => $reason,
                'returned_at' => now()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            $value = 0.0;
            $refillsBack = 0;
            $refillId = Product::where('sku', Setting::get('sales', 'refill_service_sku') ?? '19L-REFILL')->value('id');

            foreach ($lines as $itemId => $qty) {
                $qty = (float) $qty;
                $item = $items[$itemId] ?? null;
                if (! $item || $qty <= 0) {
                    continue;
                }
                $left = (float) $item->quantity - (float) $item->returned_qty;
                if ($qty > $left + 0.0001) {
                    throw ValidationException::withMessages(['items' => "{$item->product?->name}: only {$left} can be returned."]);
                }

                $lineValue = round($qty * ((float) $item->line_total / (float) $item->quantity) * $factor, 2);
                $value += $lineValue;
                $item->update(['returned_qty' => (float) $item->returned_qty + $qty]);

                $return->items()->create([
                    'invoice_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $qty,
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineValue,
                ]);

                $this->restock($invoice, $item->product, $item->variant_id, $qty, $return);
                if ($item->product_id === $refillId) {
                    $refillsBack += (int) $qty;
                }
            }

            $value = round($value, 2);
            if ($value <= 0) {
                throw ValidationException::withMessages(['items' => 'Select at least one item to return.']);
            }

            // A returned refill hands the filled company bottle back.
            if ($refillsBack > 0 && $invoice->bottles_delivered !== null) {
                $this->restockBottles($invoice, $refillsBack, $return);
            }

            // Money: first wipe udhaar still due on this bill, then refund the rest.
            $reduceDue = round(min($value, max(0.0, (float) $invoice->balance)), 2);
            $refund = round($value - $reduceDue, 2);
            $customer = $invoice->customer_id ? Customer::find($invoice->customer_id) : null;

            if ($customer && $reduceDue > 0) {
                $this->ledger->post($customer, 0, $reduceDue, $return, "Return {$return->return_number} (udhaar reduced)");
            }
            if ($refund > 0) {
                if ($refundMethod === 'advance') {
                    // Kept as the customer's advance — used on their next bill.
                    $this->ledger->post($customer, 0, $refund, $return, "Return {$return->return_number} (kept as advance)");
                } else {
                    Payment::create([
                        'reference' => 'RFD-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                        'payable_type' => $invoice->getMorphClass(),
                        'payable_id' => $invoice->getKey(),
                        'amount' => $refund,
                        'method' => $refundMethod,
                        'direction' => 'out',
                        'paid_at' => now()->toDateString(),
                        'note' => "Refund {$return->return_number}",
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $return->update(['total' => $value]);

            $allBack = $invoice->items()->get()->every(fn ($i) => (float) $i->returned_qty >= (float) $i->quantity);
            $invoice->update([
                'returned_total' => round((float) $invoice->returned_total + $value, 2),
                'balance' => round((float) $invoice->balance - $reduceDue, 2),
                'status' => $allBack ? ($cancel ? 'cancelled' : 'returned') : ((float) $invoice->balance - $reduceDue <= 0 ? 'paid' : $invoice->status),
            ]);

            return $return->load('items');
        });
    }

    /** @return array<int, float> every line's not-yet-returned quantity */
    private function allRemaining(Invoice $invoice): array
    {
        return $invoice->items()->get()
            ->mapWithKeys(fn ($i) => [$i->id => (float) $i->quantity - (float) $i->returned_qty])
            ->filter(fn ($q) => $q > 0)->all();
    }

    /** Put back what a sale line took out: a pack returns pack_size base bottles; services hold no stock. */
    private function restock(Invoice $invoice, ?Product $product, ?int $variantId, float $qty, SalesReturn $return): void
    {
        if ($product?->isPack()) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $product->pack_of_product_id,
                'type' => StockMovementType::Return,
                'quantity' => $qty * max(1, (int) $product->pack_size),
                'reference' => $return,
                'note' => "Pack ({$product->pack_size}) — Return {$return->return_number}",
            ]);
        } elseif ($product && $product->track_inventory && ! $product->is_service) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'type' => StockMovementType::Return,
                'quantity' => $qty,
                'reference' => $return,
                'note' => "Return {$return->return_number}",
            ]);
        }
    }

    /** Refills returned: the filled company bottles come back to the shop and off the customer. */
    private function restockBottles(Invoice $invoice, int $count, SalesReturn $return): void
    {
        $bottle = Product::where('sku', Setting::get('sales', 'bottle_19l_sku') ?? '19L-BOTTLE')->first();
        if ($bottle && $bottle->track_inventory) {
            $this->inventory->move([
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $bottle->id,
                'type' => StockMovementType::Return,
                'quantity' => $count,
                'reference' => $return,
                'note' => "19L bottles back — Return {$return->return_number}",
            ]);
        }
        if ($invoice->customer_id) {
            $this->gallons->record(['customer_id' => $invoice->customer_id, 'type' => 'return', 'quantity' => $count, 'note' => $return->return_number]);
        }
        $invoice->update(['bottles_delivered' => max(0, (int) $invoice->bottles_delivered - $count)]);
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

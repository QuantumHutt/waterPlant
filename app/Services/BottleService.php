<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGallonBalance;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Enums\StockMovementType;
use Illuminate\Validation\ValidationException;

/**
 * Company 19L bottles that are out with customers:
 *  - empties brought back without a refill
 *  - lost / broken: charge the customer (bill) or write it off (loss in profit)
 *  - deposit bottle handed back: deposit refunded
 */
class BottleService extends BaseService
{
    public function __construct(
        private readonly GallonService $gallons,
        private readonly InventoryService $inventory,
        private readonly SaleService $sales,
    ) {}

    /** Empty bottles returned (no refill taken): back in shop stock, off the customer. */
    public function returnEmpties(Customer $customer, int $qty, int $warehouseId): void
    {
        $this->transaction(function () use ($customer, $qty, $warehouseId): void {
            $this->guardHeld($customer, $qty);
            if ($bottle = $this->bottle()) {
                $this->inventory->move([
                    'warehouse_id' => $warehouseId, 'product_id' => $bottle->id, 'type' => StockMovementType::Return,
                    'quantity' => $qty, 'reference' => $customer, 'note' => "Empty 19L returned — {$customer->name}",
                ]);
            }
            $this->gallons->record(['customer_id' => $customer->id, 'type' => 'return', 'quantity' => $qty, 'note' => 'Empties returned (no refill)']);
        });
    }

    /**
     * Lost or broken company bottle(s).
     *  $chargeBy null      → written off: bottle cost counted as a loss in profit
     *  $chargeBy cash|bank → customer pays now (a bill for the bottle)
     *  $chargeBy udhaar    → added to the customer's udhaar
     *  $chargeBy deposit   → taken from the deposit they paid for the bottle
     */
    public function lostOrDamaged(Customer $customer, string $type, int $qty, ?string $chargeBy, float $pricePerBottle, int $warehouseId): ?Invoice
    {
        return $this->transaction(function () use ($customer, $type, $qty, $chargeBy, $pricePerBottle, $warehouseId): ?Invoice {
            $this->guardHeld($customer, $qty);
            $invoice = null;

            if ($chargeBy) {
                $amount = round($pricePerBottle * $qty, 2);
                if ($chargeBy === 'deposit') {
                    $held = (float) (CustomerGallonBalance::where('customer_id', $customer->id)->value('deposit_held') ?? 0);
                    if ($amount > $held) {
                        throw ValidationException::withMessages(['amount' => 'Deposit sirf Rs '.number_format($held, 0).' hai.']);
                    }
                }
                $charge = Product::where('sku', 'BOTTLE-CHARGE')->firstOrFail();
                $invoice = $this->sales->create([
                    'warehouse_id' => $warehouseId,
                    'customer_id' => $customer->id,
                    'type' => 'pos',
                    'paid_total' => in_array($chargeBy, ['cash', 'bank'], true) ? $amount : 0,
                    'payment_method' => $chargeBy === 'bank' ? 'bank' : 'cash',
                    'note' => ($type === 'missing' ? 'Lost' : 'Broken')." 19L bottle × {$qty}",
                    'items' => [['product_id' => $charge->id, 'quantity' => $qty, 'unit_price' => $pricePerBottle]],
                ]);
                if ($chargeBy === 'deposit') {
                    // Paid out of the deposit: no new cash; deposit held goes down.
                    $this->sales->recordPayment($invoice, $amount, 'deposit');
                    $this->gallons->record(['customer_id' => $customer->id, 'type' => 'deposit_refund', 'deposit_amount' => $amount, 'note' => "Kept for {$invoice->invoice_number}"]);
                }
            }

            $this->gallons->record([
                'customer_id' => $customer->id,
                'type' => $type,                         // missing | damaged
                'quantity' => $qty,
                'charge_invoice_id' => $invoice?->id,
                'loss_value' => $invoice ? 0 : round(((float) ($this->bottle()?->cost_price ?? 0)) * $qty, 2),
                'note' => $invoice ? "Charged on {$invoice->invoice_number}" : 'Written off',
            ]);

            return $invoice;
        });
    }

    /** Deposit bottle handed back → back in stock + deposit refunded (cash / bank / advance). */
    public function returnDepositBottle(Customer $customer, int $qty, float $amount, string $method, int $warehouseId): void
    {
        $this->guardHeld($customer, $qty);
        $held = (float) (CustomerGallonBalance::where('customer_id', $customer->id)->value('deposit_held') ?? 0);
        if ($amount > $held + 0.001) {
            throw ValidationException::withMessages(['amount' => 'Deposit sirf Rs '.number_format($held, 0).' jama hai.']);
        }
        $this->sales->refundDeposit($customer, $qty, $amount, $method, $warehouseId, "Deposit refund — {$customer->name}");
    }

    private function guardHeld(Customer $customer, int $qty): void
    {
        $held = (int) (CustomerGallonBalance::where('customer_id', $customer->id)->value('held_qty') ?? 0);
        if ($qty < 1 || $qty > $held) {
            throw ValidationException::withMessages(['quantity' => "{$customer->name} ke paas sirf {$held} company bottle(s) hain."]);
        }
    }

    private function bottle(): ?Product
    {
        $bottle = Product::where('sku', Setting::get('sales', 'bottle_19l_sku') ?? '19L-BOTTLE')->first();

        return $bottle && $bottle->track_inventory ? $bottle : null;
    }
}

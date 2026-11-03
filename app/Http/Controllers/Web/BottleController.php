<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\BottleService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** 19L bottles with a customer: empties back · lost / broken · deposit bottle returned. */
class BottleController extends Controller
{
    public function __construct(private readonly BottleService $bottles) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:return,missing,damaged,deposit_refund'],
            'quantity' => ['required', 'integer', 'min:1'],
            'charge_by' => ['nullable', 'in:cash,bank,udhaar,deposit'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', 'in:cash,bank,advance'],
        ]);
        // Empties back is everyday POS work; losses and deposit refunds are for bottle managers.
        abort_unless($request->user()->can($data['action'] === 'return' ? 'sales.create' : 'gallons.manage'), 403);

        $warehouseId = (int) (Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id'));
        $qty = (int) $data['quantity'];

        $msg = match ($data['action']) {
            'return' => (function () use ($customer, $qty, $warehouseId) {
                $this->bottles->returnEmpties($customer, $qty, $warehouseId);

                return "✓ {$qty} empty bottle(s) back from {$customer->name}";
            })(),
            'missing', 'damaged' => (function () use ($customer, $data, $qty, $warehouseId) {
                $price = (float) ($data['price'] ?? Setting::get('sales', 'bottle_charge') ?? 1300);
                $invoice = $this->bottles->lostOrDamaged($customer, $data['action'], $qty, $data['charge_by'] ?? null, $price, $warehouseId);
                $what = $data['action'] === 'missing' ? 'lost' : 'broken';

                return $invoice
                    ? "✓ {$qty} {$what} bottle(s) charged ".Money::format((float) $invoice->grand_total)." — {$invoice->invoice_number}"
                    : "✓ {$qty} {$what} bottle(s) written off as loss";
            })(),
            'deposit_refund' => (function () use ($customer, $data, $qty, $warehouseId) {
                $amount = (float) ($data['amount'] ?? 0);
                $this->bottles->returnDepositBottle($customer, $qty, $amount, $data['method'] ?? 'cash', $warehouseId);

                return "✓ {$qty} bottle(s) back — deposit ".Money::format($amount).' refunded';
            })(),
        };

        return back()->with('success', $msg);
    }
}

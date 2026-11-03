<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('sales.view'), 403);

        return Inertia::render('Sales/Index', [
            'invoices' => $this->service->list((int) $request->integer('per_page', 15), $request->only(['status', 'type'])),
            // current_balance = udhaar owed (shown at the POS; salesman can collect it).
            'customers' => Customer::where('is_active', true)->get(['id', 'name', 'type', 'phone', 'current_balance']),
            'warehouses' => Warehouse::where('is_active', true)->get(['id', 'name']),
            // Sealed sellable products only (excludes bulk water + the company bottle, which have no sale price).
            'products' => $this->sellableProducts(),
            // 19L refill service (deducts water only) + deposit config for the new-bottle action.
            'refillService' => Product::where('sku', \App\Models\Setting::get('sales', 'refill_service_sku') ?? '19L-REFILL')
                ->first(['id', 'name', 'sku', 'sale_price']),
            // Cooler refill — amount typed in per sale (any POS user).
            'coolerRefill' => Product::where('sku', 'COOLER-REFILL')->where('is_active', true)->first(['id', 'name', 'sku']),
            // How many of each product sold today (shown on the POS tiles).
            'todayCounts' => \Illuminate\Support\Facades\DB::table('invoice_items')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->whereDate('invoices.invoice_date', now()->toDateString())
                ->whereNotIn('invoices.status', ['cancelled', 'returned'])
                ->groupBy('invoice_items.product_id')
                ->selectRaw('invoice_items.product_id, SUM(invoice_items.quantity) as q')
                ->pluck('q', 'product_id')->map(fn ($q) => (float) $q),
            'bottleDeposit' => (float) (\App\Models\Setting::get('sales', 'bottle_deposit') ?? 1000),
        ]);
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Without the discount permission (e.g. salesman) the server enforces list prices —
        // a price or discount typed into the browser is ignored.
        if (! $request->user()->can('sales.discount')) {
            $prices = Product::whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
            foreach ($data['items'] as &$line) {
                if ($prices[$line['product_id']]->is_manual_price) {
                    $line['discount'] = 0;   // amount typed at the counter (cooler refill) is kept
                    continue;
                }
                $line['unit_price'] = $prices[$line['product_id']]->priceFor($data['type']);
                $line['discount'] = 0;
            }
            unset($line);
            $data['discount'] = 0;
        }

        $invoice = $this->service->create($data);

        return back()
            ->with('success', "✓ Sale complete — {$invoice->invoice_number} · ".\App\Support\Money::format((float) $invoice->grand_total))
            ->with('print_invoice_id', $invoice->id);
    }

    /**
     * Sellable products for the POS with live on-hand stock.
     * Single → its own bottle stock; Pack → available whole packs (base ÷ pack_size).
     *
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     */
    private function sellableProducts(): \Illuminate\Support\Collection
    {
        $onHand = \Illuminate\Support\Facades\DB::table('stock_items')
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as q')
            ->pluck('q', 'product_id');

        // Cost is owner data (bill-at-cost / below-cost warning) — never sent to a cashier's browser.
        $showCost = auth()->user()?->hasRole(\App\Support\Enums\RoleName::SuperAdmin->value) ?? false;

        return Product::where('is_active', true)
            ->where('is_service', false)
            ->where('sale_price', '>', 0)
            ->get(['id', 'name', 'sku', 'barcode', 'sale_price', 'wholesale_price', 'cost_price', 'pack_size', 'pack_of_product_id'])
            ->map(function ($p) use ($onHand, $showCost) {
                $baseId = $p->pack_of_product_id ?? $p->id;
                $baseQty = (float) ($onHand[$baseId] ?? 0);
                $stock = $p->pack_of_product_id
                    ? (int) floor($baseQty / max(1, (int) $p->pack_size)) // available whole packs
                    : $baseQty;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'sale_price' => (float) $p->sale_price,
                    'wholesale_price' => $p->wholesale_price !== null ? (float) $p->wholesale_price : null,
                    'cost_price' => $showCost && $p->cost_price !== null ? (float) $p->cost_price : null,
                    'pack_size' => (int) $p->pack_size,
                    'is_pack' => $p->pack_of_product_id !== null,
                    'stock' => $stock,
                ];
            });
    }

    /** Thermal receipt (58mm / 80mm) — standalone print page. */
    public function receipt(Request $request, Invoice $invoice): \Illuminate\View\View
    {
        abort_unless($request->user()->can('sales.view'), 403);

        $invoice->load(['items.product', 'customer']);
        $size = $request->integer('size', 80) === 58 ? 58 : 80;

        return view('receipts.thermal', [
            'invoice' => $invoice,
            'size' => $size,
            'company' => \App\Models\Setting::allGrouped()['company'] ?? [],
            'cashier' => $request->user()->name,
        ]);
    }

    /** Colorful, branded A4 invoice (normal printer / PDF). */
    public function invoice(Request $request, Invoice $invoice): \Illuminate\View\View
    {
        abort_unless($request->user()->can('sales.view'), 403);

        $invoice->load(['items.product', 'customer']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => \App\Models\Setting::allGrouped()['company'] ?? [],
            'cashier' => $request->user()->name,
        ]);
    }

    public function payment(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.round(max(0.0, (float) $invoice->balance), 2)], // no overpayment
            'method' => ['nullable', 'in:cash,bank,cheque,card,credit'],
        ]);
        $this->service->recordPayment($invoice, (float) $data['amount'], $data['method'] ?? 'cash');

        return back()->with('success', 'Payment recorded.');
    }

    public function returnInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('sales.return'), 403);
        $this->service->returnInvoice($invoice, $request->input('reason'));

        return back()->with('success', "Invoice {$invoice->invoice_number} returned.");
    }
}

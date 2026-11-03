<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $service,
        private readonly \App\Services\SupplierService $suppliers,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        return Inertia::render('Purchases/Index', [
            'orders' => $this->service->list((int) $request->integer('per_page', 15), $request->only(['status'])),
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->get(['id', 'name']),
            // Things you actually buy and stock: empty bottles, caps, 19L bottles, sealed bottles.
            // (Packs and services hold no stock of their own.)
            'products' => Product::where('is_active', true)->where('is_service', false)->whereNull('pack_of_product_id')
                ->where('track_inventory', true)->orderBy('name')->get(['id', 'name', 'sku', 'cost_price']),
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $po = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $po = $this->service->create($data);
            if (! empty($data['receive_now'])) {
                abort_unless($request->user()->can('purchases.receive'), 403);
                $this->service->receive($po->fresh('items'));
                // Paid on the spot → money out of drawer / bank, supplier balance reduced.
                $paid = round(min((float) ($data['paid_amount'] ?? 0), (float) $po->grand_total), 2);
                if ($paid > 0) {
                    $this->suppliers->recordPayment(\App\Models\Supplier::find($po->supplier_id), $paid, "Paid on {$po->po_number}", $data['paid_method'] ?? 'cash');
                }
            }

            return $po->fresh();
        });

        $msg = $po->status === 'received' ? "✓ {$po->po_number} received — stock added" : "✓ Purchase order {$po->po_number} created";

        return back()->with('success', $msg);
    }

    public function receive(Request $request, PurchaseOrder $purchase): RedirectResponse
    {
        abort_unless($request->user()->can('purchases.receive'), 403);
        $this->service->receive($purchase);

        return back()->with('success', "Goods received for {$purchase->po_number}.");
    }
}

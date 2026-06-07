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
    public function __construct(private readonly PurchaseService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        return Inertia::render('Purchases/Index', [
            'orders' => $this->service->list((int) $request->integer('per_page', 15), $request->only(['status'])),
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->get(['id', 'name']),
            'products' => Product::where('is_active', true)->get(['id', 'name', 'sku', 'cost_price']),
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return back()->with('success', 'Purchase order created.');
    }

    public function receive(Request $request, PurchaseOrder $purchase): RedirectResponse
    {
        abort_unless($request->user()->can('purchases.receive'), 403);
        $this->service->receive($purchase);

        return back()->with('success', "Goods received for {$purchase->po_number}.");
    }
}

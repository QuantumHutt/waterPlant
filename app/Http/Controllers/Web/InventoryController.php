<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Requests\Inventory\StockMoveRequest;
use App\Http\Requests\Inventory\StockTransferRequest;
use App\Http\Resources\StockItemResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Repositories\Contracts\StockItemRepositoryInterface;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockItemRepositoryInterface $stockItems,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('inventory.view'), 403);

        $items = $this->stockItems->paginate(
            (int) $request->integer('per_page', 20),
            $request->only(['warehouse_id', 'search', 'low_stock']),
            ['product', 'variant', 'warehouse'],
        );

        return Inertia::render('Inventory/Index', [
            'stock' => StockItemResource::collection($items),
            'filters' => $request->only(['warehouse_id', 'search', 'low_stock']),
            'warehouses' => WarehouseResource::collection(Warehouse::where('is_active', true)->get()),
            'products' => Product::where('is_active', true)->where('track_inventory', true)
                ->get(['id', 'name', 'sku', 'cost_price', 'pack_size']),
        ]);
    }

    public function stockIn(StockMoveRequest $request): RedirectResponse
    {
        $this->inventory->stockIn(
            (int) $request->integer('warehouse_id'),
            (int) $request->integer('product_id'),
            (float) $request->input('quantity'),
            (float) $request->input('unit_cost', 0),
            $request->filled('variant_id') ? (int) $request->integer('variant_id') : null,
            $request->input('note'),
        );

        return back()->with('success', 'Stock added.');
    }

    public function stockOut(StockMoveRequest $request): RedirectResponse
    {
        $this->inventory->stockOut(
            (int) $request->integer('warehouse_id'),
            (int) $request->integer('product_id'),
            (float) $request->input('quantity'),
            $request->filled('variant_id') ? (int) $request->integer('variant_id') : null,
            $request->input('note'),
        );

        return back()->with('success', 'Stock issued.');
    }

    public function transfer(StockTransferRequest $request): RedirectResponse
    {
        $this->inventory->transfer(
            (int) $request->integer('from_warehouse_id'),
            (int) $request->integer('to_warehouse_id'),
            $request->input('items'),
            $request->input('note'),
        );

        return back()->with('success', 'Stock transferred.');
    }

    public function adjust(StockAdjustmentRequest $request): RedirectResponse
    {
        $this->inventory->adjust(
            (int) $request->integer('warehouse_id'),
            $request->string('type')->toString(),
            $request->input('items'),
            $request->input('reason'),
        );

        return back()->with('success', 'Stock adjusted.');
    }
}

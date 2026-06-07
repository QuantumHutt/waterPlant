<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Requests\Inventory\StockMoveRequest;
use App\Http\Requests\Inventory\StockTransferRequest;
use App\Http\Resources\StockItemResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Repositories\Contracts\StockItemRepositoryInterface;
use App\Services\InventoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockItemRepositoryInterface $stockItems,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('inventory.view'), 403);

        $items = $this->stockItems->paginate(
            (int) $request->integer('per_page', 20),
            $request->only(['warehouse_id', 'search', 'low_stock']),
            ['product', 'variant', 'warehouse'],
        );

        return ApiResponse::success(StockItemResource::collection($items), meta: [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'total' => $items->total(),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('inventory.view'), 403);

        $movements = StockMovement::with(['product', 'variant', 'warehouse'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->latest('moved_at')
            ->paginate((int) $request->integer('per_page', 20));

        return ApiResponse::success(StockMovementResource::collection($movements));
    }

    public function stockIn(StockMoveRequest $request): JsonResponse
    {
        $item = $this->inventory->stockIn(
            (int) $request->integer('warehouse_id'),
            (int) $request->integer('product_id'),
            (float) $request->input('quantity'),
            (float) $request->input('unit_cost', 0),
            $request->filled('variant_id') ? (int) $request->integer('variant_id') : null,
            $request->input('note'),
        );

        return ApiResponse::success(new StockItemResource($item->load(['product', 'variant'])), 'Stock added');
    }

    public function stockOut(StockMoveRequest $request): JsonResponse
    {
        $item = $this->inventory->stockOut(
            (int) $request->integer('warehouse_id'),
            (int) $request->integer('product_id'),
            (float) $request->input('quantity'),
            $request->filled('variant_id') ? (int) $request->integer('variant_id') : null,
            $request->input('note'),
        );

        return ApiResponse::success(new StockItemResource($item->load(['product', 'variant'])), 'Stock issued');
    }

    public function transfer(StockTransferRequest $request): JsonResponse
    {
        $transfer = $this->inventory->transfer(
            (int) $request->integer('from_warehouse_id'),
            (int) $request->integer('to_warehouse_id'),
            $request->input('items'),
            $request->input('note'),
        );

        return ApiResponse::created(['reference' => $transfer->reference], 'Stock transferred');
    }

    public function adjust(StockAdjustmentRequest $request): JsonResponse
    {
        $adjustment = $this->inventory->adjust(
            (int) $request->integer('warehouse_id'),
            $request->string('type')->toString(),
            $request->input('items'),
            $request->input('reason'),
        );

        return ApiResponse::created(['reference' => $adjustment->reference], 'Stock adjusted');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InternalConsumption;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InternalConsumptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InternalConsumptionController extends Controller
{
    public function __construct(private readonly InternalConsumptionService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('consumption.view'), 403);

        $list = $this->service->list((int) $request->integer('per_page', 20));

        return Inertia::render('Consumption/Index', [
            'consumptions' => $list,
            'warehouses' => Warehouse::where('is_active', true)->get(['id', 'name']),
            'products' => Product::where('is_active', true)->where('is_service', false)
                ->where('track_inventory', true)->get(['id', 'name', 'sku', 'cost_price']),
            'summary' => [
                'total_cost' => (float) InternalConsumption::sum('total_cost'),
                'total_paid' => (float) InternalConsumption::sum('amount_paid'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('consumption.create'), 403);

        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'pay_at_cost' => ['boolean'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->record($data);

        return back()->with('success', 'Internal consumption recorded (no sale, no profit).');
    }

    /** Record the POS cart as FREE internal use (Super Admin only). */
    public function pos(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(\App\Support\Enums\RoleName::SuperAdmin->value), 403);

        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $this->service->record([
                    'warehouse_id' => $data['warehouse_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'reason' => $data['reason'] ?? 'Owner internal use (POS)',
                ]);
            }
        });

        return back()->with('success', 'Internal use recorded — stock deducted, no sale.');
    }
}


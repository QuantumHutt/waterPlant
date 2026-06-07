<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\ProductionRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function __construct(private readonly ProductionService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('production.view'), 403);

        return Inertia::render('Production/Index', [
            'batches' => $this->service->list((int) $request->integer('per_page', 15)),
            'warehouses' => Warehouse::where('is_active', true)->get(['id', 'name']),
            'finishedProducts' => Product::where('is_active', true)->where('type', 'finished')->get(['id', 'name', 'sku']),
            'rawProducts' => Product::where('is_active', true)->whereIn('type', ['raw', 'consumable'])->get(['id', 'name', 'sku']),
        ]);
    }

    public function store(ProductionRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return back()->with('success', 'Production batch recorded.');
    }
}

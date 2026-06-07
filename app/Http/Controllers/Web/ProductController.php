<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use App\Support\Enums\ProductType;
use App\Support\Enums\ProductUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->service->list(
            (int) $request->integer('per_page', 15),
            $request->only(['search', 'category_id', 'type', 'is_active']),
        );

        return Inertia::render('Products/Index', [
            'products' => ProductResource::collection($products),
            'filters' => $request->only(['search', 'category_id', 'type']),
            'categories' => ProductCategoryResource::collection(ProductCategory::where('is_active', true)->orderBy('name')->get()),
            'types' => collect(ProductType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'units' => collect(ProductUnit::cases())->map(fn ($u) => ['value' => $u->value, 'label' => $u->label()]),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return back()->with('success', 'Product created successfully.');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->update($product, $request->validated());

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->service->delete($product);

        return back()->with('success', 'Product deleted successfully.');
    }
}

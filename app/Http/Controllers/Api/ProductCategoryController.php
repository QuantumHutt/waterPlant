<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryRepositoryInterface $categories) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('products.view'), 403);

        $items = $this->categories->paginate(
            (int) $request->integer('per_page', 50),
            $request->only(['search', 'is_active']),
        );

        return ApiResponse::success(ProductCategoryResource::collection($items));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return ApiResponse::created(new ProductCategoryResource($category), 'Category created');
    }

    public function destroy(Request $request, ProductCategory $category): JsonResponse
    {
        abort_unless($request->user()->can('products.delete'), 403);

        if ($category->products()->exists()) {
            return ApiResponse::error('Cannot delete a category that has products.', 422);
        }

        $this->categories->delete($category);

        return ApiResponse::success(null, 'Category deleted');
    }
}

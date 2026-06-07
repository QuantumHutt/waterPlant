<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreCategoryRequest;
use App\Models\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryRepositoryInterface $categories) {}

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->validated());

        return back()->with('success', 'Category created successfully.');
    }

    public function destroy(Request $request, ProductCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('products.delete'), 403);

        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete a category that has products.');
        }

        $this->categories->delete($category);

        return back()->with('success', 'Category deleted.');
    }
}

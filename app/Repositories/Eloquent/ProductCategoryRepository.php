<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;

class ProductCategoryRepository extends BaseRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(ProductCategory $model)
    {
        parent::__construct($model);
    }

    /** @return list<string> */
    protected function searchableColumns(): array
    {
        return ['name', 'slug'];
    }
}

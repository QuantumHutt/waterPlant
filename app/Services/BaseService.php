<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    /**
     * Run a closure inside a database transaction.
     *
     * @template T
     * @param Closure():T $callback
     * @return T
     */
    protected function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}

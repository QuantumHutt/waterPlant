<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /** @param array<string> $relations */
    public function all(array $relations = []): Collection;

    /** @param array<string,mixed> $filters */
    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator;

    /** @param array<string> $relations */
    public function find(int $id, array $relations = []): ?Model;

    /** @param array<string> $relations */
    public function findOrFail(int $id, array $relations = []): Model;

    public function findBy(string $column, mixed $value): ?Model;

    /** @param array<string,mixed> $data */
    public function create(array $data): Model;

    /** @param array<string,mixed> $data */
    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;

    public function query(): \Illuminate\Database\Eloquent\Builder;
}

<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model) {}

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $relations = []): Collection
    {
        return $this->query()->with($relations)->latest()->get();
    }

    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator
    {
        $query = $this->query()->with($relations);
        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id, array $relations = []): ?Model
    {
        return $this->query()->with($relations)->find($id);
    }

    public function findOrFail(int $id, array $relations = []): Model
    {
        return $this->query()->with($relations)->findOrFail($id);
    }

    public function findBy(string $column, mixed $value): ?Model
    {
        return $this->query()->where($column, $value)->first();
    }

    public function create(array $data): Model
    {
        return $this->model->newQuery()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Override in concrete repositories to map request filters to where-clauses.
     * Default supports a generic `search` against {@see searchableColumns()}.
     *
     * @param array<string,mixed> $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search']) && $this->searchableColumns() !== []) {
            $term = (string) $filters['search'];
            $query->where(function (Builder $q) use ($term): void {
                foreach ($this->searchableColumns() as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }
    }

    /** @return list<string> */
    protected function searchableColumns(): array
    {
        return [];
    }
}

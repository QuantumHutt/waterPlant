<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /** @param array<string,mixed> $filters */
    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->users->paginate($perPage, $filters, ['roles']);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): User
    {
        return $this->transaction(function () use ($data): User {
            $roles = $data['roles'] ?? [];
            unset($data['roles']);

            $data['password'] = Hash::make($data['password']);

            /** @var User $user */
            $user = $this->users->create($data);
            $user->syncRoles($roles);

            return $user->load('roles');
        });
    }

    /** @param array<string,mixed> $data */
    public function update(User $user, array $data): User
    {
        return $this->transaction(function () use ($user, $data): User {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $roles = $data['roles'] ?? null;
            unset($data['roles']);

            $this->users->update($user, $data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            return $user->load('roles');
        });
    }

    public function delete(User $user): bool
    {
        return $this->users->delete($user);
    }
}

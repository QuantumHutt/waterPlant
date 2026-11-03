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
        return $this->users->paginate($perPage, $filters, ['roles', 'permissions']);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): User
    {
        return $this->transaction(function () use ($data): User {
            $roles = $data['roles'] ?? [];
            $permissions = $data['permissions'] ?? [];
            unset($data['roles'], $data['permissions']);

            $data['password'] = Hash::make($data['password']);

            /** @var User $user */
            $user = $this->users->create($data);
            $user->syncRoles($roles);
            $user->syncPermissions($permissions);   // extra, on top of the role

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
            $permissions = $data['permissions'] ?? null;
            unset($data['roles'], $data['permissions']);

            $this->users->update($user, $data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }
            if ($permissions !== null) {
                $user->syncPermissions($permissions);   // extra, on top of the role
            }

            return $user->load('roles');
        });
    }

    public function delete(User $user): bool
    {
        return $this->users->delete($user);
    }
}

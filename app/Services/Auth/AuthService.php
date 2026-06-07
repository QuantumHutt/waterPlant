<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService extends BaseService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Validate credentials and issue a Sanctum API token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function loginWithToken(string $email, string $password, string $deviceName = 'api'): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account is disabled.',
            ]);
        }

        $this->recordLogin($user);

        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user->load('roles'), 'token' => $token];
    }

    public function recordLogin(User $user): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();
    }
}

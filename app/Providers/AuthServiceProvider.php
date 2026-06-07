<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use App\Support\Enums\RoleName;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        User::class => UserPolicy::class,
        Product::class => ProductPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Super Admin bypasses every authorization check.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Enums\RoleName;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Owner edits what each role can do. Super Admin always has everything. */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('roles.view'), 403);

        return Inertia::render('Roles/Index', [
            'roles' => Role::with('permissions:id,name')->withCount('users')->orderBy('id')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'label' => RoleName::tryFrom($r->name)?->label() ?? $r->name,
                    'locked' => $r->name === RoleName::SuperAdmin->value,
                    'users_count' => $r->users_count,
                    'permissions' => $r->name === RoleName::SuperAdmin->value ? Permissions::all() : $r->permissions->pluck('name'),
                ]),
            'permissionGroups' => Permissions::GROUPS,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.update'), 403);
        abort_if($role->name === RoleName::SuperAdmin->value, 422, 'Super Admin always has every permission.');

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);
        $role->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $label = RoleName::tryFrom($role->name)?->label() ?? $role->name;

        return back()->with('success', "✓ {$label} permissions saved");
    }
}

<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount('users')
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'is_protected' => $role->name === 'admin',
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('admin/roles/create', [
            'permissions' => $this->permissionsGrouped(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });

        return to_route('admin.roles.index')
            ->with('success', "Rol {$role->name} creado.");
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
            ],
            'permissions' => $this->permissionsGrouped(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($role, $data): void {
            $role->name = $data['name'];
            $role->save();

            $role->syncPermissions($data['permissions'] ?? []);
        });

        return to_route('admin.roles.index')
            ->with('success', "Rol {$role->name} actualizado.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $name = $role->name;
        $role->delete();

        return to_route('admin.roles.index')
            ->with('success', "Rol {$name} eliminado.");
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    protected function permissionsGrouped(): array
    {
        $all = Permission::all();

        return $all
            ->groupBy(fn (Permission $p): string => explode(' ', $p->name, 2)[1] ?? $p->name)
            ->map(fn ($group, $resource): array => $group
                ->sortBy(fn (Permission $p): string => $p->name)
                ->map(fn (Permission $p): array => [
                    'value' => $p->name,
                    'label' => ucfirst(explode(' ', $p->name, 2)[0] ?? $p->name),
                ])
                ->values()
                ->all()
            )
            ->sortKeys()
            ->all();
    }
}

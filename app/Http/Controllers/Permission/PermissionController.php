<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\StorePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $p): array => [
                'id' => $p->id,
                'name' => $p->name,
            ]);

        $roles = Role::query()->orderBy('name')->get(['id', 'name'])->all();

        $matrix = [];
        $roleNames = collect($roles)->pluck('name');

        foreach ($permissions as $p) {
            $row = ['id' => $p['id'], 'permission' => $p['name']];
            foreach ($roleNames as $rn) {
                $row[$rn] = in_array($p['name'], $this->permsForRole($rn), true);
            }
            $matrix[] = $row;
        }

        return Inertia::render('admin/permisos/index', [
            'matrix' => $matrix,
            'roles' => $roles,
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::create([
            'name' => $request->string('name')->toString(),
            'guard_name' => 'web',
        ]);

        app()['cache']->forget('spatie.permission.cache');

        return back()->with('success', 'Permiso creado.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->authorize('delete', $permission);

        $name = $permission->name;
        $permission->delete();

        app()['cache']->forget('spatie.permission.cache');

        return back()->with('success', "Permiso {$name} eliminado.");
    }

    /**
     * @return list<string>
     */
    protected function permsForRole(string $roleName): array
    {
        $role = Role::findByName($roleName, 'web');

        return $role->permissions->pluck('name')->all();
    }
}

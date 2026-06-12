<?php

namespace App\Observers;

use App\Models\User;
use Spatie\Permission\Models\Role;

class UserObserver
{
    /**
     * Garantiza que todo User recién creado tenga al menos un rol.
     *
     * - Si el User ya viene con roles asignados (tests, factories, flujos que
     *   llaman a assignRole/syncRoles antes de guardar), se respetan.
     * - Si no, se le asigna el rol 'user' como mínimo. Si ese rol no existe
     *   (BD sin seed), se usa el primer rol disponible como fallback.
     *
     * Esto centraliza la regla "todo user necesita al menos un rol" y evita
     * olvidarla en cada punto de creación (admin, Fortify, API, widget, POS,
     * webhook Evolution, etc.).
     */
    public function created(User $user): void
    {
        if ($user->roles()->exists()) {
            return;
        }

        $defaultRole = Role::where('name', 'user')->first()
            ?? Role::orderBy('id')->first();

        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }
    }

    /**
     * Si el teléfono cambió, re-detecta el país antes de persistir.
     * Garantiza que country_code siempre esté sincronizado con phone.
     */
    public function saving(User $user): void
    {
        if ($user->isDirty('phone')) {
            $user->country_code = $user->detectCountryCode();
        }
    }
}

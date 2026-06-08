<?php

namespace App\Policies;

use App\Models\User;

class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view packages');
    }

    public function update(User $user): bool
    {
        return $user->can('update packages');
    }

    public function toggle(User $user): bool
    {
        return $user->can('toggle packages');
    }
}

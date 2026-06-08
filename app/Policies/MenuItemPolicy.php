<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function create(User $user, ?MenuItem $menuItem = null): bool
    {
        return $user->can('create menu items');
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->can('update menu items');
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->can('delete menu items');
    }
}

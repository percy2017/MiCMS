<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function create(User $user, ?MenuItem $menuItem = null): bool
    {
        return true;
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return true;
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return true;
    }
}

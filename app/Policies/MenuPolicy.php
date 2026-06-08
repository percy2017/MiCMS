<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view menus');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $user->can('view menus');
    }

    public function create(User $user): bool
    {
        return $user->can('create menus');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->can('update menus');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $user->can('delete menus');
    }
}

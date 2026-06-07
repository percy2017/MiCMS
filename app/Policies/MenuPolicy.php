<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Menu $menu): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Menu $menu): bool
    {
        return true;
    }

    public function delete(User $user, Menu $menu): bool
    {
        return true;
    }
}

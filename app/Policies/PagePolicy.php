<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view pages');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('view pages');
    }

    public function create(User $user): bool
    {
        return $user->can('create pages');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('update pages');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('delete pages');
    }

    public function setHome(User $user, Page $page): bool
    {
        return $user->can('set home pages');
    }
}

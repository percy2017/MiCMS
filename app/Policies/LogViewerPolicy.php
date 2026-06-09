<?php

namespace App\Policies;

use App\Models\User;

class LogViewerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view logs');
    }

    public function view(User $user): bool
    {
        return $user->can('view logs');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete logs');
    }
}

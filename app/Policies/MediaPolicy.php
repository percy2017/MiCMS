<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Any authenticated user can browse the media library.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user can view a single media item.
     */
    public function view(User $user, Media $media): bool
    {
        return true;
    }

    /**
     * Any authenticated user can upload new media.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user can update a media item's metadata.
     */
    public function update(User $user, Media $media): bool
    {
        return true;
    }

    /**
     * Any authenticated user can delete a media item.
     */
    public function delete(User $user, Media $media): bool
    {
        return true;
    }
}

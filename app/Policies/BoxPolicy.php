<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Box;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoxPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Box');
    }

    public function view(AuthUser $authUser, Box $box): bool
    {
        return $authUser->can('View:Box');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Box');
    }

    public function update(AuthUser $authUser, Box $box): bool
    {
        return $authUser->can('Update:Box');
    }

    public function delete(AuthUser $authUser, Box $box): bool
    {
        return $authUser->can('Delete:Box');
    }

    public function restore(AuthUser $authUser, Box $box): bool
    {
        return $authUser->can('Restore:Box');
    }

    public function forceDelete(AuthUser $authUser, Box $box): bool
    {
        return $authUser->can('ForceDelete:Box');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Box');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Box');
    }

    public function replicate(AuthUser $authUser, Box $box): bool
    {
        return $authUser->can('Replicate:Box');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Box');
    }

}

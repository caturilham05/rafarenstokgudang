<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Packer;
use Illuminate\Auth\Access\HandlesAuthorization;

class PackerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Packer');
    }

    public function view(AuthUser $authUser, Packer $packer): bool
    {
        return $authUser->can('View:Packer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Packer');
    }

    public function update(AuthUser $authUser, Packer $packer): bool
    {
        return $authUser->can('Update:Packer');
    }

    public function delete(AuthUser $authUser, Packer $packer): bool
    {
        return $authUser->can('Delete:Packer');
    }

    public function restore(AuthUser $authUser, Packer $packer): bool
    {
        return $authUser->can('Restore:Packer');
    }

    public function forceDelete(AuthUser $authUser, Packer $packer): bool
    {
        return $authUser->can('ForceDelete:Packer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Packer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Packer');
    }

    public function replicate(AuthUser $authUser, Packer $packer): bool
    {
        return $authUser->can('Replicate:Packer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Packer');
    }

}
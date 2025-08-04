<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransferPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any transfers.
     */
    public function viewAny(?User $user): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    /**
     * Determine whether the user can view the transfer.
     */
    public function view(?User $user, Transfer $transfer): bool
    {
        return true; // Public access for transfers
    }

    /**
     * Determine whether the user can create transfers.
     */
    public function create(?User $user): bool
    {
        return true; // Public access for creating transfers
    }

    /**
     * Determine whether the user can update the transfer.
     */
    public function update(?User $user, Transfer $transfer): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    /**
     * Determine whether the user can delete the transfer.
     */
    public function delete(?User $user, Transfer $transfer): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    /**
     * Determine whether the user can restore the transfer.
     */
    public function restore(?User $user, Transfer $transfer): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    /**
     * Determine whether the user can permanently delete the transfer.
     */
    public function forceDelete(?User $user, Transfer $transfer): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    /**
     * Admin functions
     */
    public function index(?User $user): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    public function show(?User $user, Transfer $transfer): bool
    {
        return $user && $user->hasPermission('admin.access');
    }

    public function destroy(?User $user, Transfer $transfer): bool
    {
        return $user && $user->hasPermission('admin.access');
    }
}

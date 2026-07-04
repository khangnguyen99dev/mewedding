<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Admins bypass every check. Returning null defers to the specific ability.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('invitations.manage');
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $this->owns($user, $invitation);
    }

    public function create(User $user): bool
    {
        return $user->can('invitations.manage');
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return $this->owns($user, $invitation);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->owns($user, $invitation);
    }

    public function duplicate(User $user, Invitation $invitation): bool
    {
        return $this->owns($user, $invitation);
    }

    public function publish(User $user, Invitation $invitation): bool
    {
        return $this->owns($user, $invitation);
    }

    protected function owns(User $user, Invitation $invitation): bool
    {
        return $user->can('invitations.manage') && $invitation->user_id === $user->id;
    }
}

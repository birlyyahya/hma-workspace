<?php

namespace App\Policies;

use App\Models\SupportPolicy;
use App\Models\User;

class SupportPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportPolicy $supportPolicy): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return (int) ($user->level ?? 0) >= 90;
    }

    public function update(User $user, SupportPolicy $supportPolicy): bool
    {
        return (int) ($user->level ?? 0) >= 90;
    }

    public function delete(User $user, SupportPolicy $supportPolicy): bool
    {
        return (int) ($user->level ?? 0) >= 90;
    }
}

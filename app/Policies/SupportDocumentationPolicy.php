<?php

namespace App\Policies;

use App\Models\SupportDocumentation;
use App\Models\User;

class SupportDocumentationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportDocumentation $supportDocumentation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('knowledge.create');
    }

    public function update(User $user, SupportDocumentation $supportDocumentation): bool
    {
        return $user->hasPermission('knowledge.update');
    }

    public function delete(User $user, SupportDocumentation $supportDocumentation): bool
    {
        return $user->hasPermission('knowledge.delete');
    }
}

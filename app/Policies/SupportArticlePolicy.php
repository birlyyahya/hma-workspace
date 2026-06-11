<?php

namespace App\Policies;

use App\Models\SupportArticle;
use App\Models\User;

class SupportArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportArticle $supportArticle): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('knowledge.create');
    }

    public function update(User $user, SupportArticle $supportArticle): bool
    {
        return $user->hasPermission('knowledge.update');
    }

    public function delete(User $user, SupportArticle $supportArticle): bool
    {
        return $user->hasPermission('knowledge.delete');
    }
}

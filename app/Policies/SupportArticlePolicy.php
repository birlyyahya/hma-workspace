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
        return true;
    }

    public function update(User $user, SupportArticle $supportArticle): bool
    {
        return $user->id === (int) $supportArticle->user_id
            || (int) ($user->level ?? 0) >= 90;
    }

    public function delete(User $user, SupportArticle $supportArticle): bool
    {
        return $user->id === (int) $supportArticle->user_id
            || (int) ($user->level ?? 0) >= 90;
    }
}

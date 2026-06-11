<?php

namespace App\Policies;

use App\Models\SupportAnnouncement;
use App\Models\User;

class SupportAnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportAnnouncement $supportAnnouncement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('knowledge.create');
    }

    public function update(User $user, SupportAnnouncement $supportAnnouncement): bool
    {
        return $user->hasPermission('knowledge.update');
    }

    public function delete(User $user, SupportAnnouncement $supportAnnouncement): bool
    {
        return $user->hasPermission('knowledge.delete');
    }
}

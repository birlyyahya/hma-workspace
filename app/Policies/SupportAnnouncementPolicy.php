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
        return (int) ($user->level ?? 0) >= 90;
    }

    public function update(User $user, SupportAnnouncement $supportAnnouncement): bool
    {
        return (int) ($user->level ?? 0) >= 90;
    }

    public function delete(User $user, SupportAnnouncement $supportAnnouncement): bool
    {
        return (int) ($user->level ?? 0) >= 90;
    }
}

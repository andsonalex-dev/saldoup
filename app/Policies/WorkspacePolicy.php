<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id
            && $user->workspaces()->where('workspaces.id', $workspace->id)
                ->wherePivot('role', WorkspaceRole::Owner->value)->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'email', 'token_hash', 'role', 'permissions', 'invited_by', 'expires_at', 'accepted_at', 'cancelled_at'])]
#[Hidden(['token_hash'])]
class WorkspaceInvitation extends Model
{
    protected function casts(): array
    {
        return ['permissions' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}

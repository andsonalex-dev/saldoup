<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['workspace_id', 'user_id', 'role', 'status', 'permissions'])]
class WorkspaceMember extends Pivot
{
    protected $table = 'workspace_members';

    public $incrementing = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['role' => WorkspaceRole::class, 'permissions' => 'array'];
    }
}

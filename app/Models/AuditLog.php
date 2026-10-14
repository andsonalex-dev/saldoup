<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'actor_id', 'entity_type', 'entity_id', 'action', 'changed_fields', 'created_at'])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['changed_fields' => 'array', 'created_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'created_by', 'name', 'type', 'visibility', 'currency', 'opening_balance'])]
class Account extends Model
{
    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }
}

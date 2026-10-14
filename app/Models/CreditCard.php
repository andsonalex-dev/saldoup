<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'created_by', 'name', 'visibility', 'credit_limit', 'closing_day', 'due_day', 'due_month_offset', 'purchase_on_closing_next'])]
class CreditCard extends Model
{
    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'closing_day' => 'integer', 'due_day' => 'integer', 'due_month_offset' => 'integer', 'purchase_on_closing_next' => 'boolean'];
    }
}

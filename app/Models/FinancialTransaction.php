<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'account_id', 'category_id', 'created_by', 'type', 'status', 'description', 'amount', 'date', 'transfer_id', 'reversal_of', 'idempotency_key', 'statement_id', 'expected_date'])]
class FinancialTransaction extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected $table = 'transactions';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'expected_date' => 'date'];
    }
}

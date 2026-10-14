<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', fn (Blueprint $t) => $t->enum('type', ['income', 'expense', 'transfer_in', 'transfer_out', 'card_payment', 'adjustment_in', 'adjustment_out'])->change());
        Schema::create('balance_adjustments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('account_id');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->decimal('target_balance', 15, 2);
            $t->string('idempotency_key', 64);
            $t->timestamps();
            $t->unique(['workspace_id', 'idempotency_key']);
            $t->foreign(['workspace_id', 'account_id'])->references(['workspace_id', 'id'])->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_adjustments');
    }
};

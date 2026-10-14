<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('name', 100);
            $t->enum('visibility', ['private', 'shared']);
            $t->decimal('credit_limit', 15, 2);
            $t->unsignedTinyInteger('closing_day');
            $t->unsignedTinyInteger('due_day');
            $t->unsignedTinyInteger('due_month_offset');
            $t->boolean('purchase_on_closing_next');
            $t->timestamps();
            $t->unique(['workspace_id', 'id']);
        });
        Schema::create('card_access', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('card_id');
            $t->unsignedBigInteger('user_id');
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->boolean('can_manage')->default(false);
            $t->timestamps();
            $t->unique(['card_id', 'user_id']);
            $t->foreign(['workspace_id', 'card_id'])->references(['workspace_id', 'id'])->on('credit_cards')->restrictOnDelete();
            $t->foreign(['workspace_id', 'user_id'])->references(['workspace_id', 'user_id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('card_statements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('card_id');
            $t->date('closing_date');
            $t->date('due_date');
            $t->timestamps();
            $t->unique(['card_id', 'closing_date']);
            $t->unique(['workspace_id', 'card_id', 'id']);
            $t->unique(['workspace_id', 'id']);
            $t->foreign(['workspace_id', 'card_id'])->references(['workspace_id', 'id'])->on('credit_cards')->restrictOnDelete();
        });
        Schema::create('card_purchases', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('card_id');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('description', 150);
            $t->decimal('amount', 15, 2);
            $t->unsignedSmallInteger('installments');
            $t->date('date');
            $t->string('idempotency_key', 64);
            $t->timestamps();
            $t->unique(['workspace_id', 'idempotency_key']);
            $t->unique(['workspace_id', 'card_id', 'id']);
            $t->foreign(['workspace_id', 'card_id'])->references(['workspace_id', 'id'])->on('credit_cards')->restrictOnDelete();
        });
        Schema::create('card_installments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('card_id');
            $t->unsignedBigInteger('purchase_id');
            $t->unsignedBigInteger('statement_id');
            $t->unsignedSmallInteger('number');
            $t->decimal('amount', 15, 2);
            $t->timestamps();
            $t->unique(['purchase_id', 'number']);
            $t->foreign(['workspace_id', 'card_id', 'purchase_id'])->references(['workspace_id', 'card_id', 'id'])->on('card_purchases')->restrictOnDelete();
            $t->foreign(['workspace_id', 'card_id', 'statement_id'])->references(['workspace_id', 'card_id', 'id'])->on('card_statements')->restrictOnDelete();
        });
        Schema::table('transactions', function (Blueprint $t) {
            $t->enum('type', ['income', 'expense', 'transfer_in', 'transfer_out', 'card_payment'])->change();
            $t->unsignedBigInteger('statement_id')->nullable();
            $t->foreign(['workspace_id', 'statement_id'])->references(['workspace_id', 'id'])->on('card_statements')->restrictOnDelete();
        });
        Schema::create('recurrence_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 64);
            $t->string('payload_hash', 64);
            $t->unsignedSmallInteger('occurrences');
            $t->timestamps();
            $t->unique(['workspace_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_batches');
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropForeign(['workspace_id', 'statement_id']);
            $t->dropColumn('statement_id');
        });
        foreach (['card_installments', 'card_purchases', 'card_statements', 'card_access', 'credit_cards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

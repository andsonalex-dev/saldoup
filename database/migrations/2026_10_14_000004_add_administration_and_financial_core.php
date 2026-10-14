<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false);
            $t->boolean('is_active')->default(true);
        });
        Schema::table('workspace_members', fn (Blueprint $t) => $t->json('permissions')->nullable());
        Schema::create('workspace_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->string('email');
            $t->string('token_hash', 64)->unique();
            $t->enum('role', ['administrator', 'member', 'reader']);
            $t->json('permissions');
            $t->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'email']);
        });
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('name', 100);
            $t->enum('type', ['checking', 'savings', 'cash', 'investment']);
            $t->enum('visibility', ['private', 'shared']);
            $t->char('currency', 3)->default('BRL');
            $t->decimal('opening_balance', 15, 2);
            $t->timestamps();
            $t->unique(['workspace_id', 'id']);
            $t->index(['workspace_id', 'visibility', 'created_by']);
        });
        Schema::create('account_access', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('account_id');
            $t->unsignedBigInteger('user_id');
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->boolean('can_manage')->default(false);
            $t->timestamps();
            $t->unique(['account_id', 'user_id']);
            $t->foreign(['workspace_id', 'account_id'])->references(['workspace_id', 'id'])->on('accounts')->restrictOnDelete();
            $t->foreign(['workspace_id', 'user_id'])->references(['workspace_id', 'user_id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
            $t->unique(['workspace_id', 'id']);
        });
        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 64);
            $t->timestamps();
            $t->unique(['workspace_id', 'idempotency_key']);
            $t->unique(['workspace_id', 'id']);
        });
        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('account_id');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->enum('type', ['income', 'expense', 'transfer_in', 'transfer_out']);
            $t->enum('status', ['planned', 'posted', 'cancelled']);
            $t->string('description', 150);
            $t->decimal('amount', 15, 2);
            $t->date('date');
            $t->unsignedBigInteger('transfer_id')->nullable();
            $t->foreignId('reversal_of')->nullable()->constrained('transactions')->restrictOnDelete();
            $t->string('idempotency_key', 64);
            $t->timestamps();
            $t->unique(['workspace_id', 'idempotency_key']);
            $t->unique('reversal_of');
            $t->foreign(['workspace_id', 'account_id'])->references(['workspace_id', 'id'])->on('accounts')->restrictOnDelete();
            $t->foreign(['workspace_id', 'category_id'])->references(['workspace_id', 'id'])->on('categories')->restrictOnDelete();
            $t->foreign(['workspace_id', 'transfer_id'])->references(['workspace_id', 'id'])->on('transfers')->restrictOnDelete();
            $t->index(['workspace_id', 'status', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['transactions', 'transfers', 'categories', 'account_access', 'accounts', 'workspace_invitations'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('workspace_members', fn (Blueprint $t) => $t->dropColumn('permissions'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_platform_admin', 'is_active']));
    }
};

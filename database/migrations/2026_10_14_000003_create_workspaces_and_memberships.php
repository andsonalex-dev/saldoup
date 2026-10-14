<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('timezone', 64);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'administrator', 'member', 'reader']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status', 'workspace_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 50);
            $table->json('changed_fields');
            $table->timestamp('created_at');
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 50);
            $table->json('changed_fields');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('plan', 20)->default('starter');
            $t->string('billing_email')->nullable();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });

        Schema::create('members', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20);
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('role', 20);
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('environments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);
            $t->string('name');
            $t->timestamps();
            $t->unique(['workspace_id', 'type']);
        });

        Schema::create('api_keys', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('key_prefix', 16);
            $t->char('key_hash', 64)->unique();
            $t->string('last4', 4);
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80);
            $t->string('target_type', 60)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'api_keys', 'environments', 'invitations', 'members', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

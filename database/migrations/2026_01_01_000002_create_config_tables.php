<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_providers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 20);
            $t->string('provider', 30);
            $t->string('name');
            $t->text('credentials')->nullable(); // encrypted:array
            $t->boolean('is_default')->default(false);
            $t->string('status', 20)->default('active');
            $t->char('webhook_token', 40)->unique();
            $t->timestamp('last_tested_at')->nullable();
            $t->timestamps();
            $t->index(['environment_id', 'channel', 'is_default']);
        });

        Schema::create('workflows', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('trigger_key', 100);
            $t->string('status', 20)->default('DRAFT');
            $t->string('priority', 10)->default('NORMAL');
            $t->boolean('is_critical')->default(false);
            $t->uuid('published_version_id')->nullable();
            $t->timestamps();
            $t->unique(['environment_id', 'trigger_key']);
        });

        Schema::create('workflow_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at');
            $t->timestamps();
            $t->unique(['workflow_id', 'version']);
        });

        Schema::create('workflow_steps', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('step_order');
            $t->string('type', 20);
            $t->string('name');
            $t->string('channel', 20)->nullable();
            $t->uuid('channel_provider_id')->nullable();
            $t->json('config')->nullable();
            $t->uuid('fallback_step_id')->nullable();
            $t->timestamps();
            $t->index(['workflow_id', 'step_order']);
        });

        Schema::create('step_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $t->text('subject')->nullable();
            $t->mediumText('body');
            $t->mediumText('body_html')->nullable();
            $t->string('locale', 10)->default('id');
            $t->timestamps();
            $t->unique(['workflow_step_id', 'locale']);
        });

        Schema::create('webhook_subscriptions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->string('url', 500);
            $t->text('secret');
            $t->json('events');
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('consecutive_failures')->default(0);
            $t->timestamp('disabled_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['webhook_subscriptions', 'step_templates', 'workflow_steps', 'workflow_versions', 'workflows', 'channel_providers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

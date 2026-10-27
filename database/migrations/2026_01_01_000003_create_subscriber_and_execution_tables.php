<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->string('external_id', 191);
            $t->string('email')->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('telegram_chat_id', 64)->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('locale', 10)->default('id');
            $t->string('timezone', 64)->default('Asia/Jakarta');
            $t->json('data')->nullable();
            $t->timestamps();
            $t->unique(['environment_id', 'external_id']);
            $t->index(['environment_id', 'email']);
            $t->index(['environment_id', 'phone']);
        });

        Schema::create('subscriber_preferences', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $t->uuid('workflow_id')->nullable();
            $t->string('channel', 20);
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->unique(['subscriber_id', 'workflow_id', 'channel']);
        });

        Schema::create('notification_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('workflow_version_id')->constrained('workflow_versions');
            $t->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $t->string('idempotency_key', 191)->nullable();
            $t->char('request_hash', 64)->nullable();
            $t->json('payload');
            $t->json('overrides')->nullable();
            $t->string('source', 20)->default('api');
            $t->boolean('is_test')->default(false);
            $t->boolean('dry_run')->default(false);
            $t->timestamps();
            $t->unique(['environment_id', 'idempotency_key']);
            $t->index(['environment_id', 'created_at']);
            $t->index(['subscriber_id', 'created_at']);
        });

        Schema::create('workflow_executions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('notification_event_id')->unique()->constrained('notification_events')->cascadeOnDelete();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('PENDING');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['environment_id', 'status', 'created_at']);
        });

        Schema::create('step_executions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('workflow_execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $t->uuid('workflow_step_id');
            $t->unsignedSmallInteger('step_order');
            $t->unsignedSmallInteger('resume_after_order')->nullable();
            $t->string('type', 20);
            $t->string('status', 20)->default('PENDING');
            $t->string('skip_reason', 40)->nullable();
            $t->string('rendered_subject', 500)->nullable();
            $t->mediumText('rendered_content')->nullable();
            $t->mediumText('rendered_html')->nullable();
            $t->timestamp('run_at')->nullable();
            $t->timestamp('executed_at')->nullable();
            $t->timestamps();
            $t->index(['workflow_execution_id', 'step_order']);
            $t->index(['status', 'run_at']);
        });

        Schema::create('message_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('step_execution_id')->unique()->constrained('step_executions')->cascadeOnDelete();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->uuid('subscriber_id')->nullable()->index();
            $t->uuid('workflow_id')->nullable()->index();
            $t->string('channel_type', 30);
            $t->string('provider', 40)->nullable();
            $t->string('destination_masked', 191);
            $t->string('status', 20)->default('QUEUED');
            $t->string('external_message_id', 191)->nullable()->index();
            $t->string('error_code', 60)->nullable();
            $t->text('error_message')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamps();
            $t->index(['environment_id', 'status', 'created_at']);
            $t->index(['environment_id', 'channel_type', 'created_at']);
        });

        Schema::create('delivery_attempts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('message_log_id')->constrained('message_logs')->cascadeOnDelete();
            $t->unsignedTinyInteger('attempt');
            $t->boolean('success');
            $t->boolean('retryable')->default(false);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->json('raw_trace')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('in_app_messages', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $t->string('title', 200);
            $t->text('body');
            $t->string('action_url', 500)->nullable();
            $t->json('data')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['subscriber_id', 'read_at', 'created_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUuid('webhook_subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
            $t->string('event_type', 60);
            $t->json('payload');
            $t->string('status', 20)->default('pending');
            $t->unsignedTinyInteger('attempt')->default(0);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('response_snippet', 500)->nullable();
            $t->timestamp('next_retry_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('usage_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedBigInteger('events_received')->default(0);
            $t->unsignedBigInteger('messages_sent')->default(0);
            $t->unsignedBigInteger('messages_failed')->default(0);
            $t->timestamps();
            $t->unique(['environment_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['usage_counters', 'webhook_deliveries', 'in_app_messages', 'delivery_attempts', 'message_logs', 'step_executions', 'workflow_executions', 'notification_events', 'subscriber_preferences', 'subscribers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

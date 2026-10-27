<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false)->after('password');
            $t->timestamp('suspended_at')->nullable();
            $t->string('suspended_reason')->nullable();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
        });

        Schema::table('workspaces', function (Blueprint $t) {
            $t->timestamp('suspended_at')->nullable();
            $t->string('suspended_reason')->nullable();
            $t->json('custom_limits')->nullable();   // {events, rpm, retention_days} menimpa plan
            $t->text('admin_notes')->nullable();
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->unsignedBigInteger('subtotal')->default(0)->after('amount');
            $t->unsignedBigInteger('discount')->default(0)->after('subtotal');
            $t->string('coupon_code', 40)->nullable()->after('discount');
            $t->timestamp('refunded_at')->nullable();
            $t->string('refund_note')->nullable();
            $t->string('manual_note')->nullable();
        });

        // Katalog plan yang dapat diedit admin (menimpa config/notifora.php saat runtime)
        Schema::create('plans', function (Blueprint $t) {
            $t->string('key', 20)->primary();
            $t->string('label', 40);
            $t->unsignedBigInteger('price')->default(0);
            $t->unsignedBigInteger('events');
            $t->unsignedInteger('rpm');
            $t->unsignedSmallInteger('retention_days');
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 40)->unique();
            $t->string('description')->nullable();
            $t->string('type', 10);                       // percent | fixed
            $t->unsignedBigInteger('value');
            $t->unsignedInteger('max_redemptions')->nullable();
            $t->unsignedSmallInteger('per_workspace_limit')->default(1);
            $t->unsignedTinyInteger('min_months')->default(1);
            $t->json('plans')->nullable();                // null = semua plan
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUuid('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['coupon_id', 'payment_id']);
        });

        Schema::create('platform_audit_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80);
            $t->string('target_type', 60)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->uuid('workspace_id')->nullable()->index();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['action', 'created_at']);
        });

        Schema::create('email_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('kind', 40)->index();
            $t->string('recipient', 255);
            $t->string('subject', 255);
            $t->string('status', 12)->default('queued');   // queued | sent | failed | skipped
            $t->text('error')->nullable();
            $t->uuid('workspace_id')->nullable()->index();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['status', 'created_at']);
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->string('kind', 40)->primary();
            $t->boolean('enabled')->default(true);
            $t->string('subject_prefix', 60)->nullable();
            $t->text('extra_note')->nullable();
            $t->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('announcements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('title', 140);
            $t->text('body');
            $t->string('tone', 10)->default('info');       // info | warn | danger | success
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('subject', 180);
            $t->text('body');
            $t->string('audience', 30)->default('all');    // all | paid | free | plan:{key}
            $t->unsignedInteger('recipients')->default(0);
            $t->unsignedInteger('delivered')->default(0);
            $t->string('status', 12)->default('sending');  // sending | sent
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('blocked_entities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type', 16);                         // ip | email_domain
            $t->string('value', 191);
            $t->string('reason')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        foreach (['blocked_entities', 'broadcasts', 'announcements', 'platform_settings', 'email_templates', 'email_logs', 'platform_audit_logs', 'coupon_redemptions', 'coupons', 'plans'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['subtotal', 'discount', 'coupon_code', 'refunded_at', 'refund_note', 'manual_note']));
        Schema::table('workspaces', fn (Blueprint $t) => $t->dropColumn(['suspended_at', 'suspended_reason', 'custom_limits', 'admin_notes']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_platform_admin', 'suspended_at', 'suspended_reason', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at', 'last_login_ip']));
    }
};

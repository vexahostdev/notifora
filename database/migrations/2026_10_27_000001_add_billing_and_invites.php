<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->timestamp('plan_expires_at')->nullable()->after('plan');
            $t->timestamp('plan_started_at')->nullable()->after('plan');
        });

        Schema::table('invitations', function (Blueprint $t) {
            $t->foreignId('invited_by')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number', 32)->unique();              // NTF-INV-2026-000001
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('plan', 20);
            $t->unsignedTinyInteger('months');
            $t->unsignedBigInteger('amount');                // IDR, tanpa desimal
            $t->string('currency', 3)->default('IDR');
            $t->string('status', 20)->default('PENDING');    // PENDING|PAID|EXPIRED|FAILED|CANCELED
            $t->string('provider', 20)->default('xendit');
            $t->string('external_id', 64)->unique();         // NTF-... (prefix routing VexaHost)
            $t->string('provider_invoice_id', 64)->nullable()->index();
            $t->text('invoice_url')->nullable();
            $t->string('payment_method', 40)->nullable();
            $t->string('payment_channel', 40)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('applied_at')->nullable();         // plan sudah diterapkan ke workspace (idempotensi)
            $t->string('failure_reason', 120)->nullable();
            $t->json('raw')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('provider', 20);
            $t->string('event_id', 128);                     // invoice id + status → idempotensi
            $t->string('event_type', 40);
            $t->uuid('payment_id')->nullable()->index();
            $t->string('processing_status', 20)->default('received'); // received|processed|ignored|failed
            $t->text('processing_error')->nullable();
            $t->string('ip', 45)->nullable();
            $t->json('payload');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::table('invitations', fn (Blueprint $t) => $t->dropConstrainedForeignId('invited_by'));
        Schema::table('workspaces', fn (Blueprint $t) => $t->dropColumn(['plan_expires_at', 'plan_started_at']));
    }
};

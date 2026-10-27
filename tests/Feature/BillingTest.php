<?php

use App\Enums\MemberRole;
use App\Exceptions\ApiException;
use App\Mail\SystemMail;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    config([
        'services.xendit.secret_key' => 'xnd_development_test',
        'services.xendit.webhook_token' => 'cb-token',
        'services.xendit.internal_secret' => 'internal-xyz',
    ]);
    $this->t = makeTenant();
    $this->ws = $this->t['env']->workspace;
    $this->ws->update(['plan' => 'free']);
    $this->actingAs($this->t['user']);
});

function fakeInvoice(string $status = 'PENDING', string $id = 'inv_123', ?int $amount = null): void
{
    Http::fake([
        'api.xendit.co/v2/invoices' => Http::response(['id' => $id, 'invoice_url' => "https://checkout.xendit.co/web/$id", 'status' => 'PENDING'], 200),
        "api.xendit.co/v2/invoices/$id" => fn () => Http::response(['id' => $id, 'status' => $status, 'amount' => $amount, 'paid_amount' => $amount, 'payment_channel' => 'QRIS'], 200),
    ]);
}

function checkout(string $plan = 'pro', int $months = 3)
{
    return test()->post('/console/billing/checkout', ['plan' => $plan, 'months' => $months, 'method' => 'qris', 'terms' => '1']);
}

function callback(array $payload, array $headers = ['X-Internal-Token' => 'internal-xyz'])
{
    return test()->postJson('/api/payment/xendit/callback', $payload, $headers);
}

function paidPayload(Payment $p, array $over = []): array
{
    return ['id' => $p->provider_invoice_id, 'external_id' => $p->external_id, 'status' => 'PAID', 'paid_amount' => $p->amount, 'amount' => $p->amount, 'payment_channel' => 'QRIS', ...$over];
}

it('renders billing, checkout and creates a Xendit invoice then redirects to it', function () {
    $this->get('/console/billing')->assertOk()->assertSee('Pilih plan')->assertSee('Pro');
    $this->get('/console/billing/checkout/pro')->assertOk()->assertSee('Bayar sekarang');

    fakeInvoice();
    checkout('pro', 3)->assertRedirect('https://checkout.xendit.co/web/inv_123');

    $p = Payment::first();
    expect($p->amount)->toBe(299000 * 3)
        ->and($p->status)->toBe('PENDING')
        ->and($p->external_id)->toStartWith('NTF-INV-'.now()->year)
        ->and($p->number)->toMatch('/^NTF-INV-\d{4}-\d{6}$/')
        ->and($p->provider_invoice_id)->toBe('inv_123');

    Http::assertSent(fn ($r) => $r->url() === 'https://api.xendit.co/v2/invoices'
        && $r['amount'] === 897000 && str_starts_with($r['external_id'], 'NTF-') && $r['payment_methods'] === ['QRIS']
        && $r['currency'] === 'IDR' && $r['metadata']['payment_id'] === $p->id);
    Mail::assertQueued(SystemMail::class, fn ($m) => $m->kind === 'invoice-created');
});

it('only owners can purchase and validates input', function () {
    Member::where('user_id', $this->t['user']->id)->update(['role' => MemberRole::ADMIN->value]);
    $this->get('/console/billing')->assertOk();
    $this->get('/console/billing/checkout/pro')->assertForbidden();
    checkout()->assertForbidden();

    Member::where('user_id', $this->t['user']->id)->update(['role' => MemberRole::OWNER->value]);
    $this->post('/console/billing/checkout', ['plan' => 'nope', 'months' => 3, 'method' => 'qris', 'terms' => '1'])->assertSessionHasErrors('plan');
    $this->post('/console/billing/checkout', ['plan' => 'pro', 'months' => 5, 'method' => 'qris', 'terms' => '1'])->assertSessionHasErrors('months');
    $this->post('/console/billing/checkout', ['plan' => 'pro', 'months' => 1, 'method' => 'qris'])->assertSessionHasErrors('terms');
    expect(Payment::count())->toBe(0);
});

it('fails safely when Xendit is not configured or rejects the invoice', function () {
    config(['services.xendit.secret_key' => null]);
    checkout()->assertSessionHasErrors('checkout');
    expect(Payment::first()->status)->toBe('FAILED');

    config(['services.xendit.secret_key' => 'xnd_development_test']);
    Http::fake(['api.xendit.co/*' => Http::response(['message' => 'INVALID_AMOUNT'], 400)]);
    checkout()->assertSessionHasErrors('checkout');
    expect(Payment::count())->toBe(2)->and(Payment::latest('created_at')->first()->status)->toBe('FAILED');
});

it('settles a payment from the VexaHost-forwarded callback and activates the plan', function () {
    fakeInvoice('PAID', 'inv_123', 897000);
    checkout('pro', 3);
    $p = Payment::first();

    callback(paidPayload($p))->assertOk()->assertJsonPath('status', 'success');

    $p->refresh();
    $this->ws->refresh();
    expect($p->status)->toBe('PAID')->and($p->applied_at)->not->toBeNull()->and($p->payment_channel)->toBe('QRIS')
        ->and($this->ws->plan)->toBe('pro')
        ->and($this->ws->plan_expires_at->isSameDay(now()->addMonths(3)))->toBeTrue()
        ->and($this->ws->effectivePlanKey())->toBe('pro');
    Mail::assertQueued(SystemMail::class, fn ($m) => $m->kind === 'payment-success');
    expect(PaymentEvent::first()->processing_status)->toBe('processed');
});

it('is idempotent: replayed callbacks never extend the plan twice', function () {
    fakeInvoice('PAID', 'inv_123', 299000);
    checkout('pro', 1);
    $p = Payment::first();

    callback(paidPayload($p))->assertOk();
    $expiry = $this->ws->fresh()->plan_expires_at->timestamp;

    callback(paidPayload($p))->assertOk()->assertJsonPath('status', 'already_processed');
    callback(paidPayload($p, ['status' => 'SETTLED']))->assertOk(); // status lain, tetap tidak menambah masa berlaku
    expect($this->ws->fresh()->plan_expires_at->timestamp)->toBe($expiry);
    expect(Mail::queued(SystemMail::class, fn ($m) => $m->kind === 'payment-success')->count())->toBe(1);
});

it('authenticates callbacks: internal token or callback token, never open', function () {
    fakeInvoice('PAID', 'inv_123', 299000);
    checkout('pro', 1);
    $p = Payment::first();

    callback(paidPayload($p), [])->assertForbidden();
    callback(paidPayload($p), ['X-Internal-Token' => 'wrong'])->assertForbidden();
    callback(paidPayload($p), ['x-callback-token' => 'wrong'])->assertForbidden();
    expect($p->fresh()->status)->toBe('PENDING');

    callback(paidPayload($p), ['x-callback-token' => 'cb-token'])->assertOk();
    expect($p->fresh()->status)->toBe('PAID');

    config(['services.xendit.internal_secret' => null, 'services.xendit.webhook_token' => null]);
    callback(paidPayload($p), ['X-Internal-Token' => ''])->assertStatus(500);
    $this->getJson('/api/payment/xendit/callback')->assertOk()->assertJsonPath('status', 'ok');
});

it('rejects amount mismatches and callbacks Xendit itself does not confirm', function () {
    fakeInvoice('PAID', 'inv_123', 299000);
    checkout('pro', 1);
    $p = Payment::first();

    // Tanpa kunci Xendit (tidak ada verifikasi ulang): nominal payload harus sama persis
    config(['services.xendit.secret_key' => null]);
    callback(paidPayload($p, ['paid_amount' => 1000, 'amount' => 1000]))->assertStatus(422);
    expect($p->fresh()->status)->toBe('PENDING');

    // Dengan kunci Xendit: nominal diambil dari Xendit (bukan payload yang bisa dipalsukan)
    config(['services.xendit.secret_key' => 'xnd_development_test']);
    Http::swap(new Factory);
    Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'status' => 'PAID', 'paid_amount' => 1000, 'amount' => 1000], 200)]);
    callback(paidPayload($p))->assertStatus(422);
    expect($p->fresh()->status)->toBe('PENDING');

    // forged callback: Xendit API says invoice is still PENDING
    Http::swap(new Factory);
    Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'status' => 'PENDING', 'amount' => 299000], 200)]);
    callback(paidPayload($p, ['status' => 'PAID']), ['X-Internal-Token' => 'internal-xyz'])->assertStatus(422);
    expect($p->fresh()->status)->toBe('PENDING');
    expect($this->ws->fresh()->plan)->toBe('free');

    // Xendit API unreachable -> 503 so the sender retries
    Http::swap(new Factory);
    Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response('down', 500)]);
    callback(paidPayload($p), ['X-Internal-Token' => 'internal-xyz'])->assertStatus(503);
    expect($p->fresh()->status)->toBe('PENDING');
});

it('ignores non-Notifora and unknown payments', function () {
    callback(['id' => 'x', 'external_id' => 'VH-ORD-1-123', 'status' => 'PAID'])->assertOk()->assertJsonPath('status', 'ignored');
    callback(['id' => 'x', 'external_id' => 'NTF-INV-2026-999999-ABCD', 'status' => 'PAID', 'paid_amount' => 1])->assertOk()->assertJsonPath('status', 'not_found');
    callback(['status' => 'PING'])->assertOk();
});

it('records EXPIRED and FAILED callbacks and notifies', function () {
    fakeInvoice();
    checkout('pro', 1);
    $p = Payment::first();

    callback(['id' => 'inv_123', 'external_id' => $p->external_id, 'status' => 'EXPIRED'])->assertOk();
    expect($p->fresh()->status)->toBe('EXPIRED');
    Mail::assertQueued(SystemMail::class, fn ($m) => $m->kind === 'payment-failed');
    expect($this->ws->fresh()->plan)->toBe('free');

    // payment kedaluwarsa tidak bisa dilunasi diam-diam oleh callback PAID susulan yang tak terverifikasi
    $this->get('/console/billing/payments/'.$p->id)->assertOk()->assertSee('Invoice kedaluwarsa');
});

it('extends the same plan from its current expiry but restarts on upgrade', function () {
    $billing = app(BillingService::class);
    $this->ws->update(['plan' => 'pro', 'plan_started_at' => now(), 'plan_expires_at' => now()->addDays(10)]);
    $expiry = $this->ws->plan_expires_at->copy();

    fakeInvoice('PAID', 'inv_a', 299000);
    $renew = $billing->createCheckout($this->ws->fresh(), $this->t['user'], 'pro', 1, 'qris');
    $billing->settle($renew);
    expect($this->ws->fresh()->plan_expires_at->isSameDay($expiry->copy()->addMonth()))->toBeTrue();

    fakeInvoice('PAID', 'inv_b', 799000);
    $up = $billing->createCheckout($this->ws->fresh(), $this->t['user'], 'business', 1, 'qris');
    $billing->settle($up);
    $ws = $this->ws->fresh();
    expect($ws->plan)->toBe('business')->and($ws->plan_expires_at->isSameDay(now()->addMonth()))->toBeTrue();

    // downgrade saat plan aktif ditolak
    $this->get('/console/billing/checkout/pro')->assertOk()->assertSee('tidak dapat dibeli');
    expect(fn () => $billing->createCheckout($ws, $this->t['user'], 'starter', 1, 'qris'))->toThrow(ApiException::class);
});

it('syncs a pending payment when the user returns from Xendit even if the webhook is late', function () {
    fakeInvoice('PAID', 'inv_123', 299000);
    checkout('pro', 1);
    $p = Payment::first();
    expect($p->status)->toBe('PENDING');

    $this->get('/console/billing/payments/'.$p->id)->assertOk()->assertSee('Pembayaran diterima');
    expect($p->fresh()->status)->toBe('PAID')->and($this->ws->fresh()->plan)->toBe('pro');
    $this->getJson('/console/billing/payments/'.$p->id.'/status')->assertJson(['status' => 'PAID', 'paid' => true, 'final' => true]);
});

it('hides other workspaces payments', function () {
    fakeInvoice();
    checkout();
    $p = Payment::first();
    $other = makeTenant();
    $this->actingAs($other['user'])->get('/console/billing/payments/'.$p->id)->assertNotFound();
});

it('lets owners simulate payment only outside production', function () {
    fakeInvoice();
    checkout('pro', 1);
    $p = Payment::first();
    $this->get('/console/billing/payments/'.$p->id)->assertOk()->assertSee('Simulasikan pembayaran');
    $this->post('/console/billing/payments/'.$p->id.'/simulate')->assertRedirect();
    expect($p->fresh()->status)->toBe('PAID')->and($p->fresh()->payment_channel)->toBe('SIMULATED');

    app()->detectEnvironment(fn () => 'production');
    fakeInvoice('PENDING', 'inv_zzz');
    checkout('pro', 1);
    $p2 = Payment::latest('created_at')->first();
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/console/billing/payments/'.$p2->id.'/simulate')->assertNotFound();
});

it('cancels a pending invoice', function () {
    fakeInvoice();
    checkout();
    $p = Payment::first();
    $this->post('/console/billing/payments/'.$p->id.'/cancel')->assertRedirect('/console/billing');
    expect($p->fresh()->status)->toBe('CANCELED');
});

it('falls back to the free plan limits after expiry plus grace, with a grace window before that', function () {
    config(['notifora.billing.grace_days' => 3]);
    $this->ws->update(['plan' => 'pro', 'plan_expires_at' => now()->subDay()]);
    expect($this->ws->fresh()->inGrace())->toBeTrue()->and($this->ws->fresh()->planConfig()['label'])->toBe('Pro');

    $this->ws->update(['plan_expires_at' => now()->subDays(4)]);
    $ws = $this->ws->fresh();
    expect($ws->effectivePlanKey())->toBe('free')->and($ws->planConfig()['rpm'])->toBe(120)->and($ws->planConfig()['events'])->toBe(1000);

    // plan manual tanpa tanggal tidak kedaluwarsa
    $this->ws->update(['plan' => 'business', 'plan_expires_at' => null]);
    expect($this->ws->fresh()->effectivePlanKey())->toBe('business');
});

it('sweeps: reminders once, grace notice, downgrade, and stale invoices', function () {
    config(['notifora.billing.grace_days' => 3]);
    Cache::flush();
    $billing = app(BillingService::class);

    // pengingat H-7 (sekali saja)
    $this->ws->update(['plan' => 'pro', 'plan_expires_at' => now()->addDays(7)->subHours(2)]);
    expect($billing->sweep()['reminders'])->toBe(1);
    expect($billing->sweep()['reminders'])->toBe(0);
    Mail::assertQueued(SystemMail::class, fn ($m) => $m->kind === 'subscription-expiring');

    // sudah berakhir -> masa tenggang, notifikasi sekali
    $this->ws->update(['plan_expires_at' => now()->subHours(3)]);
    expect($billing->sweep()['graceStarted'])->toBe(1);
    expect($billing->sweep()['graceStarted'])->toBe(0);
    expect($this->ws->fresh()->plan)->toBe('pro');
    Mail::assertQueued(SystemMail::class, fn ($m) => $m->kind === 'subscription-expired');

    // lewat tenggang -> free
    $this->ws->update(['plan_expires_at' => now()->subDays(4)]);
    expect($billing->sweep()['downgraded'])->toBe(1);
    expect($this->ws->fresh()->plan)->toBe('free')->and($this->ws->fresh()->plan_expires_at)->toBeNull();
    Mail::assertQueued(SystemMail::class, fn ($m) => $m->kind === 'subscription-downgraded');

    // invoice basi
    fakeInvoice('EXPIRED', 'inv_old');
    checkout('pro', 1);
    Payment::first()->update(['expires_at' => now()->subHour()]);
    expect($billing->sweep()['expired'])->toBe(1);
    expect(Payment::first()->fresh()->status)->toBe('EXPIRED');
});

it('applies the plan limits to the API after payment', function () {
    $key = $this->t['key'];
    fakeInvoice('PAID', 'inv_123', 299000);
    checkout('pro', 1);
    callback(paidPayload(Payment::first()))->assertOk();

    $this->withHeaders(['Authorization' => 'Bearer '.$key])->getJson('/v1/workflows')->assertOk()->assertHeader('X-RateLimit-Limit', 3000);
});

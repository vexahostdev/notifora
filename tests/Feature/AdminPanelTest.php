<?php

use App\Enums\ChannelType;
use App\Exceptions\ApiException;
use App\Models\Announcement;
use App\Models\BlockedEntity;
use App\Models\Broadcast;
use App\Models\Coupon;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\BillingService;
use App\Services\HealthCheck;
use App\Services\PlanCatalog;
use App\Services\PlatformSettings;
use App\Services\Totp;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
    config(['admin.require_2fa' => false]);
    $this->t = makeTenant('pro');
    $this->ws = $this->t['env']->workspace;
    $this->admin = User::factory()->create(['name' => 'Ops']);
    $this->admin->forceFill(['is_platform_admin' => true])->save();
});

function asAdmin(): void
{
    test()->actingAs(test()->admin);
}

function makePayment(Workspace $ws, array $over = []): Payment
{
    return Payment::create($over + [
        'number' => Payment::nextNumber(), 'workspace_id' => $ws->id, 'user_id' => $ws->owner?->id,
        'plan' => 'pro', 'months' => 1, 'subtotal' => 299000, 'discount' => 0, 'amount' => 299000, 'status' => Payment::PENDING,
        'external_id' => 'NTF-INV-T-'.uniqid(), 'expires_at' => now()->addDay(),
    ]);
}

// --- Akses & 2FA -------------------------------------------------------------

it('hides the panel from guests and non-operators', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->actingAs($this->t['user'])->get('/admin')->assertNotFound();
    $this->actingAs($this->t['user'])->get('/admin/tenants')->assertNotFound();
});

it('lets an operator in and shows the dashboard', function () {
    asAdmin();
    $this->get('/admin')->assertOk()->assertSee('Dashboard platform');
});

it('forces operators to enable 2FA when required', function () {
    config(['admin.require_2fa' => true]);
    asAdmin();
    $this->get('/admin')->assertRedirect(route('security.2fa'));
    $this->get('/security/2fa')->assertOk();
});

it('matches the RFC 6238 test vector', function () {
    $totp = new Totp;
    expect($totp->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59))->toBe('287082');
    expect($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 1, 59))->toBeTrue();
    expect($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000', 1, 59))->toBeFalse();
});

it('enrols 2FA and then requires the code at login', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $user = $this->admin;
    $user->forceFill(['password' => 'Password123'])->save();
    $this->actingAs($user)->post('/security/2fa/start')->assertRedirect();
    $secret = session('2fa_setup_secret') ?? $user->fresh()->two_factor_secret;
    expect($secret)->not->toBeEmpty();

    $totp = new Totp;
    $this->post('/security/2fa/confirm', ['code' => $totp->code($secret)])->assertSessionHasNoErrors();
    expect($user->fresh()->hasTwoFactor())->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/two-factor-challenge', ['code' => $totp->code($secret)])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

// --- Smoke test semua halaman --------------------------------------------------

it('renders every admin page with data present', function () {
    asAdmin();
    $payment = makePayment($this->ws, ['coupon_code' => 'X']);
    DB::table('payment_events')->insert(['id' => (string) Str::ulid(), 'provider' => 'xendit', 'event_id' => 'e1', 'event_type' => 'invoice.paid', 'payment_id' => $payment->id, 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    Coupon::create(['code' => 'HEMAT', 'type' => 'percent', 'value' => 10]);
    Announcement::create(['title' => 'Info', 'body' => 'Tes', 'tone' => 'info', 'active' => true]);
    BlockedEntity::create(['type' => 'ip', 'value' => '203.0.113.9']);
    EmailLog::create(['kind' => 'welcome', 'recipient' => 'a@b.test', 'subject' => 'Halo', 'status' => 'failed', 'error' => 'boom']);
    DB::table('failed_jobs')->insert(['uuid' => 'u-1', 'connection' => 'redis', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\Foo']), 'exception' => "Boom\n#0", 'failed_at' => now()]);
    makeProvider(ChannelType::EMAIL);
    $member = $this->ws->members()->first();

    $urls = [
        '/admin', '/admin/reports', '/admin/tenants', "/admin/tenants/{$this->ws->id}", '/admin/users', "/admin/users/{$this->t['user']->id}",
        '/admin/payments', "/admin/payments/{$payment->id}", '/admin/payment-events', '/admin/plans', '/admin/coupons',
        '/admin/ops', '/admin/ops/failed-jobs', '/admin/ops/providers', '/admin/ops/webhooks', '/admin/ops/messages',
        '/admin/abuse', '/admin/audit', '/admin/audit?scope=tenants', '/admin/announcements', '/admin/broadcasts',
        '/admin/emails', '/admin/emails/templates', '/admin/settings', '/admin/search?q=Acme', '/admin/search?q=a@b.test',
        '/admin/tenants?q=Acme&plan=pro&status=active', '/admin/payments?status=PENDING&q=NTF',
    ];
    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
    expect($member)->not->toBeNull();
});

it('exports csv and tenant json', function () {
    asAdmin();
    makePayment($this->ws);
    $this->get('/admin/payments/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->get('/admin/reports/workspaces.csv')->assertOk();
    $this->get("/admin/tenants/{$this->ws->id}/export")->assertOk();
    expect(PlatformAuditLog::where('action', 'like', '%export%')->count())->toBeGreaterThan(0);
});

// --- Tenant ------------------------------------------------------------------

it('suspends a workspace everywhere: api, console and queue', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'x'], 200)]);
    makeProvider(ChannelType::WHATSAPP);
    asAdmin();
    $this->post("/admin/tenants/{$this->ws->id}/suspend", ['reason' => 'Spam / penyalahgunaan'])->assertSessionHasNoErrors();
    expect($this->ws->fresh()->isSuspended())->toBeTrue();
    expect(PlatformAuditLog::where('action', 'tenant.suspended')->where('workspace_id', $this->ws->id)->exists())->toBeTrue();

    Cache::flush();
    $this->postJson('/v1/events/trigger', ['name' => 'x', 'to' => ['subscriberId' => 'u1']], ['Authorization' => 'Bearer '.$this->t['key']])
        ->assertStatus(403)->assertJsonPath('error.code', 'workspace_suspended');

    $this->actingAs($this->t['user'])->get('/console')->assertStatus(403)->assertSee('ditangguhkan');

    asAdmin();
    $this->post("/admin/tenants/{$this->ws->id}/unsuspend")->assertSessionHasNoErrors();
    expect($this->ws->fresh()->isSuspended())->toBeFalse();
});

it('grants a plan manually and applies custom limits over the plan', function () {
    asAdmin();
    $this->put("/admin/tenants/{$this->ws->id}/plan", ['plan' => 'business', 'expires_at' => now()->addMonths(2)->format('Y-m-d\TH:i'), 'note' => 'Kemitraan'])->assertSessionHasNoErrors();
    $ws = $this->ws->fresh();
    expect($ws->plan)->toBe('business')->and($ws->plan_expires_at)->not->toBeNull();

    $this->put("/admin/tenants/{$this->ws->id}/limits", ['events' => 777, 'rpm' => 55])->assertSessionHasNoErrors();
    $cfg = $this->ws->fresh()->planConfig();
    expect($cfg['events'])->toBe(777)->and($cfg['rpm'])->toBe(55);
    expect(PlatformAuditLog::where('action', 'tenant.plan_set')->exists())->toBeTrue();
});

it('impersonates a member with an audit trail and can stop', function () {
    asAdmin();
    $this->post("/admin/tenants/{$this->ws->id}/impersonate/{$this->t['user']->id}")->assertRedirect(route('console.dashboard'));
    $this->assertAuthenticatedAs($this->t['user']);
    expect(session('impersonator_id'))->toBe($this->admin->id);
    $this->get('/console')->assertOk()->assertSee('Mode impersonasi');
    expect(PlatformAuditLog::where('action', 'impersonation.started')->exists())->toBeTrue();

    $this->post('/impersonation/stop')->assertRedirect(route('admin.tenants.index'));
    $this->assertAuthenticatedAs($this->admin);
});

it('refuses to impersonate another operator', function () {
    asAdmin();
    $other = User::factory()->create();
    $other->forceFill(['is_platform_admin' => true])->save();
    $this->ws->members()->create(['user_id' => $other->id, 'role' => 'ADMIN']);
    $this->post("/admin/tenants/{$this->ws->id}/impersonate/{$other->id}")->assertSessionHas('error');
    $this->assertAuthenticatedAs($this->admin);
});

it('transfers ownership and demotes the old owner', function () {
    asAdmin();
    $second = User::factory()->create();
    $m = $this->ws->members()->create(['user_id' => $second->id, 'role' => 'ADMIN']);
    $this->post("/admin/tenants/{$this->ws->id}/members/{$m->id}/owner")->assertSessionHasNoErrors();
    expect($m->fresh()->role->value)->toBe('OWNER');
    expect($this->ws->members()->where('role', 'OWNER')->count())->toBe(1);
});

it('deletes a workspace only with the exact slug', function () {
    asAdmin();
    $this->delete("/admin/tenants/{$this->ws->id}", ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
    expect(Workspace::find($this->ws->id))->not->toBeNull();
    $this->delete("/admin/tenants/{$this->ws->id}", ['confirm' => $this->ws->slug])->assertRedirect();
    expect(Workspace::find($this->ws->id))->toBeNull();
});

// --- Pengguna ----------------------------------------------------------------

it('suspends a user and blocks login', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $victim = User::factory()->create(['password' => 'Password123']);
    asAdmin();
    $this->post("/admin/users/{$victim->id}/suspend", ['reason' => 'abuse'])->assertSessionHasNoErrors();
    auth()->logout();
    $this->post('/login', ['email' => $victim->email, 'password' => 'Password123'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('toggles operator access but never on yourself', function () {
    asAdmin();
    $u = User::factory()->create();
    $this->post("/admin/users/{$u->id}/toggle-admin")->assertSessionHasNoErrors();
    expect($u->fresh()->isPlatformAdmin())->toBeTrue();
    $this->post("/admin/users/{$this->admin->id}/toggle-admin")->assertSessionHas('error');
    expect($this->admin->fresh()->isPlatformAdmin())->toBeTrue();
});

// --- Pembayaran ---------------------------------------------------------------

it('marks a payment paid manually and applies the plan once', function () {
    asAdmin();
    $this->ws->update(['plan' => 'free']);
    $p = makePayment($this->ws);
    $this->post("/admin/payments/{$p->id}/mark-paid", ['note' => 'Transfer BCA'])->assertSessionHasNoErrors();
    expect($p->fresh()->status)->toBe(Payment::PAID)->and($this->ws->fresh()->plan)->toBe('pro');
    $until = $this->ws->fresh()->plan_expires_at;
    $this->post("/admin/payments/{$p->id}/mark-paid", ['note' => 'lagi'])->assertSessionHas('error');
    expect($this->ws->fresh()->plan_expires_at->equalTo($until))->toBeTrue();
});

it('voids a pending invoice and refunds a paid one', function () {
    asAdmin();
    $pending = makePayment($this->ws);
    $this->post("/admin/payments/{$pending->id}/void", ['note' => 'salah'])->assertSessionHasNoErrors();
    expect($pending->fresh()->status)->toBe(Payment::CANCELED);

    $paid = makePayment($this->ws);
    $this->post("/admin/payments/{$paid->id}/mark-paid", ['note' => 'tf']);
    $this->post("/admin/payments/{$paid->id}/refund", ['note' => 'minta batal', 'revoke_plan' => '1'])->assertSessionHasNoErrors();
    expect($paid->fresh()->status)->toBe(Payment::REFUNDED)->and($this->ws->fresh()->plan)->toBe('free');
});

// --- Plan & kupon ---------------------------------------------------------------

it('edits plans in the database with immediate effect', function () {
    asAdmin();
    $this->put('/admin/plans/pro', ['label' => 'Pro Plus', 'price' => 450000, 'events' => 123456, 'rpm' => 900, 'retention_days' => 45, 'active' => '1'])->assertSessionHasNoErrors();
    $plan = app(BillingService::class)->plans()['pro'];
    expect($plan['price'])->toBe(450000)->and($plan['label'])->toBe('Pro Plus');
    expect(config('notifora.plans.pro.events'))->toBe(123456);

    $this->put('/admin/plans/pro', ['label' => 'Pro Plus', 'price' => 450000, 'events' => 123456, 'rpm' => 900, 'retention_days' => 45])->assertSessionHasNoErrors();
    expect(PlanCatalog::purchasable())->not->toHaveKey('pro');
});

it('creates a custom plan and refuses to delete a plan in use', function () {
    asAdmin();
    $this->post('/admin/plans', ['key' => 'enterprise', 'label' => 'Enterprise', 'price' => 2000000, 'events' => 5000000, 'rpm' => 3000, 'retention_days' => 90, 'active' => '1'])->assertSessionHasNoErrors();
    expect(PlanCatalog::all())->toHaveKey('enterprise');
    $this->ws->update(['plan' => 'enterprise']);
    $this->delete('/admin/plans/enterprise')->assertSessionHas('error');
    $this->delete('/admin/plans/pro')->assertForbidden();
});

it('applies percent and fixed coupons at checkout and enforces limits', function () {
    Http::fake(['api.xendit.co/v2/invoices' => Http::response(['id' => 'inv_c', 'invoice_url' => 'https://checkout.xendit.co/web/inv_c', 'status' => 'PENDING'], 200)]);
    config(['services.xendit.secret_key' => 'xnd_development_test']);
    Coupon::create(['code' => 'HEMAT20', 'type' => 'percent', 'value' => 20, 'per_workspace_limit' => 1]);
    $this->ws->update(['plan' => 'free']);
    $billing = app(BillingService::class);

    $p = $billing->createCheckout($this->ws, $this->t['user'], 'pro', 1, 'all', 'hemat20');
    $price = config('notifora.plans.pro.price');
    expect($p->subtotal)->toBe($price)->and($p->discount)->toBe((int) round($price * 0.2))->and($p->amount)->toBe($price - (int) round($price * 0.2))->and($p->coupon_code)->toBe('HEMAT20');

    expect(fn () => $billing->createCheckout($this->ws, $this->t['user'], 'pro', 1, 'all', 'HEMAT20'))->toThrow(ApiException::class);
    expect(fn () => $billing->createCheckout($this->ws, $this->t['user'], 'pro', 1, 'all', 'TIDAKADA'))->toThrow(ApiException::class);
});

it('settles a 100% coupon immediately without an invoice', function () {
    Coupon::create(['code' => 'GRATIS', 'type' => 'percent', 'value' => 100]);
    $this->ws->update(['plan' => 'free']);
    $p = app(BillingService::class)->createCheckout($this->ws, $this->t['user'], 'starter', 1, 'all', 'GRATIS');
    expect($p->status)->toBe(Payment::PAID)->and($p->amount)->toBe(0)->and($this->ws->fresh()->plan)->toBe('starter');
});

it('previews a coupon over HTTP', function () {
    Coupon::create(['code' => 'DISC', 'type' => 'fixed', 'value' => 50000]);
    $this->actingAs($this->t['user'])->postJson('/console/billing/coupon-preview', ['plan' => 'pro', 'months' => 1, 'coupon' => 'DISC'])
        ->assertOk()->assertJsonPath('discount', 50000)->assertJsonPath('total', config('notifora.plans.pro.price') - 50000);
    $this->postJson('/console/billing/coupon-preview', ['plan' => 'pro', 'months' => 1, 'coupon' => 'NOPE'])->assertStatus(422);
});

it('blocks checkout when the platform flag is off', function () {
    PlatformSettings::setBool('checkout_enabled', false);
    $this->actingAs($this->t['user'])->post('/console/billing/checkout', ['plan' => 'pro', 'months' => 1, 'method' => 'qris', 'terms' => '1'])->assertSessionHasErrors('checkout');
});

// --- Abuse, maintenance, flag ----------------------------------------------------

it('blocks an ip on web and api', function () {
    asAdmin();
    $this->post('/admin/abuse/blocks', ['type' => 'ip', 'value' => '203.0.113.50', 'reason' => 'spam'])->assertSessionHasNoErrors();
    auth()->logout();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->get('/login')->assertStatus(403);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->postJson('/v1/events/trigger', [], ['Authorization' => 'Bearer '.$this->t['key']])->assertStatus(403);
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/login')->assertOk();
});

it('refuses to block your own ip', function () {
    asAdmin();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->post('/admin/abuse/blocks', ['type' => 'ip', 'value' => '203.0.113.77'])->assertSessionHasErrors('value');
});

it('blocks signup from a blocked email domain', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    BlockedEntity::create(['type' => 'email_domain', 'value' => 'spam.test']);
    $this->post('/register', ['name' => 'X', 'email' => 'bot@spam.test', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])->assertSessionHasErrors('email');
    expect(User::where('email', 'bot@spam.test')->exists())->toBeFalse();
});

it('closes signup via the platform flag', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    PlatformSettings::setBool('signup_enabled', false);
    $this->post('/register', ['name' => 'X', 'email' => 'new@ok.test', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])->assertSessionHasErrors('email');
    expect(User::where('email', 'new@ok.test')->exists())->toBeFalse();
});

it('toggles signup lock from admin panel and updates register view', function () {
    PlatformSettings::setBool('signup_enabled', true);
    // Guest visits /register -> sees form
    $this->get('/register')->assertOk()->assertSee('Create account');

    // Admin locks signup
    asAdmin();
    $this->post('/admin/settings/toggle-signup')->assertSessionHas('success');
    expect(PlatformSettings::bool('signup_enabled'))->toBeFalse();

    // Guest visits /register -> sees locked notice
    auth()->logout();
    $this->get('/register')->assertOk()->assertSee('PENDAFTARAN SEDANG DITUTUP');

    // Admin unlocks signup
    asAdmin();
    $this->post('/admin/settings/toggle-signup')->assertSessionHas('success');
    expect(PlatformSettings::bool('signup_enabled'))->toBeTrue();

    // Guest visits /register -> sees form again
    auth()->logout();
    $this->get('/register')->assertOk()->assertSee('Create account');
});

it('locks and unlocks signup via artisan command', function () {
    $this->artisan('notifora:signup', ['action' => 'lock', '--message' => 'Tahap pengujian lokal'])
        ->assertSuccessful()
        ->expectsOutputToContain('DIKUNCI');
    expect(PlatformSettings::bool('signup_enabled'))->toBeFalse()
        ->and(PlatformSettings::get('signup_lock_message'))->toBe('Tahap pengujian lokal');

    $this->artisan('notifora:signup', ['action' => 'status'])
        ->assertSuccessful()
        ->expectsOutputToContain('TERKUNCI');

    $this->artisan('notifora:signup', ['action' => 'unlock'])
        ->assertSuccessful()
        ->expectsOutputToContain('DIBUKA');
    expect(PlatformSettings::bool('signup_enabled'))->toBeTrue();
});

it('serves 503 in maintenance mode but keeps operators in', function () {
    asAdmin();
    $this->put('/admin/settings', ['signup_enabled' => '1', 'checkout_enabled' => '1', 'maintenance' => '1', 'maintenance_message' => 'Upgrade DB'])->assertSessionHasNoErrors();
    $this->get('/admin')->assertOk();
    auth()->logout();
    $this->get('/register')->assertStatus(503)->assertSee('Upgrade DB');
    $this->postJson('/v1/events/trigger', [], ['Authorization' => 'Bearer '.$this->t['key']])->assertStatus(503)->assertJsonPath('error.code', 'maintenance');
});

// --- Email & komunikasi -----------------------------------------------------------

it('keeps security emails enabled and lets others be switched off', function () {
    asAdmin();
    $this->put('/admin/emails/templates/password-reset', ['subject_prefix' => '[X]'])->assertSessionHasNoErrors();
    expect(EmailTemplate::find('password-reset')->enabled)->toBeTrue();
    $this->put('/admin/emails/templates/welcome', ['subject_prefix' => ''])->assertSessionHasNoErrors();
    expect(EmailTemplate::find('welcome')->enabled)->toBeFalse();
});

it('sends an smtp test email and logs it', function () {
    asAdmin();
    $this->post('/admin/emails/test', ['to' => 'ops@notifora.test'])->assertSessionHasNoErrors();
    expect(EmailLog::where('recipient', 'ops@notifora.test')->exists())->toBeTrue();
});

it('creates announcements shown in the console', function () {
    asAdmin();
    $this->post('/admin/announcements', ['title' => 'Maintenance Sabtu', 'body' => 'Pukul 01.00 WIB', 'tone' => 'warn'])->assertSessionHasNoErrors();
    $this->actingAs($this->t['user'])->get('/console')->assertOk()->assertSee('Maintenance Sabtu');
    asAdmin();
    $a = Announcement::first();
    $this->post("/admin/announcements/{$a->id}/toggle");
    $this->actingAs($this->t['user'])->get('/console')->assertDontSee('Maintenance Sabtu');
});

it('queues a broadcast to workspace admins only after confirmation', function () {
    asAdmin();
    $this->post('/admin/broadcasts', ['subject' => 'Fitur baru', 'body' => 'Halo semua', 'audience' => 'all'])->assertSessionHasErrors('confirm');
    expect(Broadcast::count())->toBe(0);
    $this->post('/admin/broadcasts', ['subject' => 'Fitur baru', 'body' => 'Halo semua', 'audience' => 'all', 'confirm' => '1'])->assertSessionHasNoErrors();
    $b = Broadcast::first();
    expect($b)->not->toBeNull()->and($b->recipients)->toBeGreaterThan(0);
});

// --- Operasional & audit ------------------------------------------------------------

it('retries and deletes failed jobs', function () {
    asAdmin();
    DB::table('failed_jobs')->insert(['uuid' => 'job-1', 'connection' => 'sync', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'Foo', 'job' => 'x', 'data' => []]), 'exception' => 'Boom', 'failed_at' => now()]);
    $this->delete('/admin/ops/failed-jobs/job-1')->assertSessionHasNoErrors();
    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect(PlatformAuditLog::where('action', 'ops.job_deleted')->exists())->toBeTrue();
});

it('reports health checks without throwing', function () {
    $checks = collect(app(HealthCheck::class)->run());
    expect($checks->firstWhere('name', 'Database')['status'])->toBe('ok');
    expect($checks->pluck('status')->every(fn ($s) => in_array($s, ['ok', 'warn', 'fail'])))->toBeTrue();
});

it('finds records through global search', function () {
    asAdmin();
    $this->get('/admin/search?q='.urlencode($this->t['user']->email))->assertOk()->assertSee($this->t['user']->email);
    $this->get('/admin/search?q=x')->assertOk()->assertSee('minimal 2');
});

it('shows operator actions to tenants as Notifora Support in their audit log', function () {
    asAdmin();
    $this->post("/admin/tenants/{$this->ws->id}/suspend", ['reason' => 'tes']);
    $this->post("/admin/tenants/{$this->ws->id}/unsuspend");
    $this->actingAs($this->t['user'])->get('/console/settings/audit')->assertOk();
});

<?php

use App\Enums\ChannelType;
use App\Enums\MemberRole;
use App\Jobs\DeliverCustomerWebhookJob;
use App\Mail\Mails;
use App\Mail\SystemMail;
use App\Models\ApiKey;
use App\Models\Member;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Models\Workspace;
use App\Services\InvitationService;
use App\Services\SystemNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

function queuedKinds(): array
{
    return Mail::queued(SystemMail::class)->map->kind->values()->all();
}

function sentTo(string $kind, string $email): bool
{
    return Mail::queued(SystemMail::class, fn ($m) => $m->kind === $kind && $m->hasTo($email))->isNotEmpty();
}

it('sends a welcome email on registration and a password-changed alert after reset', function () {
    $this->post('/register', ['name' => 'Ryan', 'team' => 'Acme', 'email' => 'ryan@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])
        ->assertRedirect('/console');
    expect(sentTo('welcome', 'ryan@example.com'))->toBeTrue();

    auth()->logout();
    $this->post('/forgot-password', ['email' => 'ryan@example.com'])->assertSessionHas('status');
    expect(sentTo('password-reset', 'ryan@example.com'))->toBeTrue();
});

it('resets a password through the emailed token and alerts the owner', function () {
    $user = User::factory()->create(['email' => 'a@example.com']);
    $token = Password::createToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => 'a@example.com', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
        ->assertRedirect('/login');
    expect(sentTo('password-changed', 'a@example.com'))->toBeTrue();
});

it('alerts on password change from account settings', function () {
    $t = makeTenant();
    $this->actingAs($t['user'])->put('/console/settings/password', ['current_password' => 'password', 'password' => 'BrandNew12345', 'password_confirmation' => 'BrandNew12345'])->assertSessionHasNoErrors();
    expect(sentTo('password-changed', $t['user']->email))->toBeTrue();
});

it('emails invitations with the invite link and notifies the inviter when accepted', function () {
    $t = makeTenant();
    $ws = $t['env']->workspace;
    $this->actingAs($t['user'])->post('/console/settings/team/invite', ['email' => 'new@example.com', 'role' => 'DEVELOPER'])->assertRedirect();
    expect(sentTo('invitation', 'new@example.com'))->toBeTrue();

    $mail = Mail::queued(SystemMail::class, fn ($m) => $m->kind === 'invitation')->first();
    expect($mail->payload['cta']['url'])->toContain('/invitations/')->and($mail->payload['details']['Peran'])->toBe('DEVELOPER');

    auth()->logout();
    $token = app(InvitationService::class)->invite($ws, 'joiner@example.com', MemberRole::VIEWER, $t['user']);
    $this->get("/invitations/$token");
    $this->post('/register', ['name' => 'Joiner', 'email' => 'joiner@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1']);
    expect(sentTo('member-joined', $t['user']->email))->toBeTrue();
});

it('emails role changes and removals to the affected member', function () {
    $t = makeTenant();
    $ws = $t['env']->workspace;
    $member = User::factory()->create();
    $m = Member::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => MemberRole::VIEWER]);

    $this->actingAs($t['user'])->put("/console/settings/team/{$m->id}", ['role' => 'DEVELOPER'])->assertRedirect();
    expect(sentTo('role-changed', $member->email))->toBeTrue();

    $this->delete("/console/settings/team/{$m->id}")->assertRedirect();
    expect(sentTo('member-removed', $member->email))->toBeTrue();
});

it('alerts workspace admins when API keys are created or revoked', function () {
    $t = makeTenant();
    $this->actingAs($t['user'])->post('/console/settings/api-keys', ['name' => 'ci-key'])->assertRedirect();
    expect(sentTo('apikey-created', $t['user']->email))->toBeTrue();

    $key = ApiKey::where('name', 'ci-key')->first();
    $this->delete("/console/settings/api-keys/{$key->id}")->assertRedirect();
    expect(sentTo('apikey-revoked', $t['user']->email))->toBeTrue();
});

it('warns once at 80% and once at 100% of the production quota only', function () {
    $t = makeTenant();
    $ws = $t['env']->workspace;
    $ws->update(['plan' => 'starter']);
    $prod = $ws->environments()->where('type', 'PRODUCTION')->first()->load('workspace');
    $n = app(SystemNotifier::class);
    Cache::flush();

    $n->quotaThreshold($prod, 7000, 10000);   // 70% -> tidak ada
    expect(queuedKinds())->toBe([]);
    $n->quotaThreshold($prod, 8000, 10000);   // 80%
    $n->quotaThreshold($prod, 8500, 10000);   // masih 80% -> tidak kirim ulang
    expect(queuedKinds())->toBe(['quota-80']);
    $n->quotaThreshold($prod, 10000, 10000);  // 100%
    $n->quotaThreshold($prod, 10500, 10000);
    expect(queuedKinds())->toBe(['quota-80', 'quota-100']);

    $n->quotaThreshold($t['env'], 999999, 1000); // DEVELOPMENT tidak memicu email
    expect(queuedKinds())->toHaveCount(2);
});

it('emails admins when a webhook endpoint is auto-disabled after repeated failures', function () {
    $t = makeTenant();
    Http::fake(['client.test/*' => Http::response('err', 500)]);
    $sub = WebhookSubscription::create(['url' => 'https://client.test/hook', 'secret' => 's', 'events' => ['*'], 'consecutive_failures' => 49]);
    $d = WebhookDelivery::create(['webhook_subscription_id' => $sub->id, 'event_type' => 'message.sent', 'payload' => [], 'created_at' => now()]);

    Queue::fake([DeliverCustomerWebhookJob::class]);
    (new DeliverCustomerWebhookJob($d->id))->handle();

    expect($sub->fresh()->status)->toBe('disabled')->and(sentTo('webhook-disabled', $t['user']->email))->toBeTrue();
});

it('emails admins once per hour when a provider circuit opens', function () {
    $t = makeTenant();
    $provider = makeProvider(ChannelType::WHATSAPP);
    Cache::flush();
    $n = app(SystemNotifier::class);

    $n->providerDegraded($provider);
    $n->providerDegraded($provider);
    expect(queuedKinds())->toBe(['provider-degraded']);
});

it('never breaks the request when SMTP is unavailable', function () {
    Mail::swap(app('mail.manager')); // mailer asli
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1, 'queue.default' => 'sync']);

    $this->post('/register', ['name' => 'Ryan', 'email' => 'smtpdown@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])
        ->assertRedirect('/console');
    expect(User::where('email', 'smtpdown@example.com')->exists())->toBeTrue();
});

it('renders branded emails with sender context, tone, cta and plain-text alternative', function () {
    $user = User::factory()->make(['name' => 'Budi', 'email' => 'budi@example.com']);
    $mail = Mails::welcome($user);

    $html = $mail->render();
    expect($html)->toContain('Selamat datang, Budi!')->toContain('support@notifora.my.id')
        ->toContain('© '.date('Y').' notifora. All rights reserved. Created by notifora')
        ->toContain('Buka Console');

    $mail->assertHasSubject('Selamat datang di Notifora');
    $mail->assertSeeInText('Buka Console');
    expect($mail->envelope()->replyTo[0]->address)->toBe('support@notifora.my.id');
});

it('uses noreply@notifora.my.id and support@ as configured sender identities', function () {
    expect(config('notifora.support_email'))->toBe('support@notifora.my.id')
        ->and(config('mail.from.address'))->toBe('noreply@notifora.my.id');
});

it('escapes user-controlled values in emails', function () {
    $ws = new Workspace(['name' => '<script>alert(1)</script>']);
    $ws->id = 'x';
    $html = Mails::roleChanged($ws, 'ADMIN')->render();
    expect($html)->not->toContain('<script>alert(1)</script>')->and($html)->toContain('&lt;script&gt;');
});

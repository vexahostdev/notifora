<?php

use App\Enums\ChannelType;
use App\Enums\MemberRole;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ChannelProvider;
use App\Models\InAppMessage;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Workflow;
use App\Services\InvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => $this->withoutVite());

function signIn(MemberRole $role = MemberRole::OWNER): array
{
    $t = makeTenant();
    $user = $t['user'];
    if ($role !== MemberRole::OWNER) {
        Member::where('user_id', $user->id)->update(['role' => $role->value]);
    }

    return [test()->actingAs($user), $t];
}

it('serves the landing page and auth pages', function () {
    $this->get('/')->assertOk()->assertSee('ENDLESS')->assertSee('Rp 99.000');
    $this->get('/login')->assertOk()->assertSee('Establish session');
    $this->get('/register')->assertOk();
    $this->get('/forgot-password')->assertOk();
});

it('redirects guests away from the console', function () {
    $this->get('/console')->assertRedirect('/login');
});

it('registers a user with a workspace, both environments and owner role', function () {
    $this->post('/register', [
        'name' => 'Ryan', 'team' => 'Acme', 'email' => 'ryan@example.com',
        'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1',
    ])->assertRedirect('/console');

    $user = User::where('email', 'ryan@example.com')->first();
    $m = $user->currentMembership();
    expect($m->role)->toBe(MemberRole::OWNER)
        ->and($m->workspace->environments()->count())->toBe(2)
        ->and($m->workspace->name)->toBe('Acme');
    $this->get('/console')->assertOk()->assertSee('Dashboard');
});

it('rejects weak passwords and wrong credentials', function () {
    $this->post('/register', ['name' => 'x', 'email' => 'a@b.co', 'password' => 'short', 'password_confirmation' => 'short', 'terms' => '1'])
        ->assertSessionHasErrors('password');
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever12'])->assertSessionHasErrors('email');
});

it('renders every console page for an owner', function () {
    [$as, $t] = signIn();
    makeProvider(ChannelType::WHATSAPP);
    $wf = makeWorkflow([['channel' => 'WHATSAPP', 'body' => 'Halo {{ subscriber.first_name }}']]);
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'x'], 200)]);
    $this->postJson('/v1/events/trigger', triggerBody(), ['Authorization' => 'Bearer '.$t['key']])->assertStatus(202);
    $sub = Subscriber::first();
    $log = MessageLog::first();

    foreach ([
        '/console', '/console/workflows', "/console/workflows/{$wf->id}/edit", "/console/workflows/{$wf->id}/test",
        '/console/subscribers', "/console/subscribers/{$sub->id}", "/console/subscribers/{$sub->id}?reveal=1",
        '/console/channels', '/console/channels/WHATSAPP', '/console/channels/EMAIL',
        '/console/logs', "/console/logs/{$log->id}", '/console/logs?status[]=FAILED&range=7d',
        '/console/settings/api-keys', '/console/settings/webhooks', '/console/settings/team',
        '/console/settings/usage', '/console/settings/audit', '/console/settings/account',
        '/console/api/dashboard?range=7d',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('enforces roles on console routes', function () {
    [$as] = signIn(MemberRole::VIEWER);
    $this->get('/console/workflows')->assertOk();
    $this->get('/console/settings/api-keys')->assertForbidden();
    $this->get('/console/settings/team')->assertForbidden();
    $this->post('/console/workflows', ['name' => 'x', 'trigger_key' => 'x', 'priority' => 'NORMAL'])->assertForbidden();

    [$as2] = signIn(MemberRole::DEVELOPER);
    $this->get('/console/settings/api-keys')->assertOk();
    $this->get('/console/settings/team')->assertForbidden();
});

it('creates an API key once, stores only its hash, and revokes it immediately', function () {
    [$as, $t] = signIn();
    $res = $this->post('/console/settings/api-keys', ['name' => 'prod-backend'])->assertRedirect('/console/settings/api-keys');
    $plain = session('new_key');
    expect($plain)->toStartWith('ntf_test_');

    $page = $this->get('/console/settings/api-keys')->assertOk();
    $key = ApiKey::where('name', 'prod-backend')->first();
    expect($key->key_hash)->toBe(hash('sha256', $plain));

    makeProvider(ChannelType::WHATSAPP);
    makeWorkflow([['channel' => 'WHATSAPP']]);
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'x'], 200)]);
    $this->postJson('/v1/events/trigger', triggerBody(), ['Authorization' => "Bearer $plain"])->assertStatus(202);

    $this->delete("/console/settings/api-keys/{$key->id}")->assertRedirect();
    $this->postJson('/v1/events/trigger', triggerBody(), ['Authorization' => "Bearer $plain"])->assertStatus(401);
    expect(AuditLog::where('action', 'apikey.revoked')->count())->toBe(1);
});

it('saves, validates, and publishes a workflow through the editor endpoints', function () {
    [$as, $t] = signIn();
    $this->post('/console/workflows', ['name' => 'Welcome', 'trigger_key' => 'welcome', 'priority' => 'NORMAL'])->assertRedirect();
    $wf = Workflow::where('trigger_key', 'welcome')->first();

    // publish workflow kosong ditolak
    $this->postJson("/console/workflows/{$wf->id}/publish")->assertStatus(422)->assertJsonPath('ok', false);

    $payload = [
        'name' => 'Welcome', 'priority' => 'NORMAL', 'is_critical' => false,
        'steps' => [
            ['uid' => 'new-1', 'type' => 'CHANNEL', 'name' => 'WA', 'channel' => 'WHATSAPP', 'provider_id' => null, 'config' => [], 'template' => ['subject' => '', 'body' => 'Halo {{ subscriber.first_name }}', 'body_html' => ''], 'fallback_uid' => 'new-2'],
            ['uid' => 'new-2', 'type' => 'CHANNEL', 'name' => 'Mail', 'channel' => 'EMAIL', 'provider_id' => null, 'config' => [], 'template' => ['subject' => 'Selamat datang', 'body' => 'Hi', 'body_html' => ''], 'fallback_uid' => null],
        ],
    ];
    $saved = $this->putJson("/console/workflows/{$wf->id}", $payload)->assertOk()->json('steps');
    expect($saved)->toHaveCount(2)->and($saved[0]['fallback_uid'])->toBe($saved[1]['uid']);

    // urutan ulang tidak boleh bentrok unique(workflow_id, step_order)
    $reordered = $payload;
    $reordered['steps'] = [
        ['uid' => $saved[1]['uid']] + $payload['steps'][1],
        ['uid' => $saved[0]['uid']] + $payload['steps'][0] + [],
    ];
    $reordered['steps'][1]['fallback_uid'] = $saved[1]['uid'];
    $this->putJson("/console/workflows/{$wf->id}", $reordered)->assertOk();

    $this->postJson("/console/workflows/{$wf->id}/publish")->assertOk()->assertJsonPath('version', 1);
    expect($wf->fresh()->status)->toBe('ACTIVE');

    // body rusak ditolak saat publish
    $broken = $payload;
    $broken['steps'][0]['template']['body'] = 'Halo {{ payload.x';
    $this->putJson("/console/workflows/{$wf->id}", $broken)->assertOk();
    $this->postJson("/console/workflows/{$wf->id}/publish")->assertStatus(422);
});

it('runs the dry-run simulator and returns rendered steps', function () {
    [$as, $t] = signIn();
    $wf = makeWorkflow([['channel' => 'IN_APP', 'subject' => 'Hai', 'body' => 'Halo {{ subscriber.first_name }}']]);
    Http::fake();

    $id = $this->postJson("/console/workflows/{$wf->id}/test", [
        'to' => ['subscriberId' => 'sim_1', 'firstName' => 'Sari'], 'payload' => [], 'real' => false,
    ])->assertOk()->json('event_id');

    $this->getJson("/console/api/events/$id")->assertOk()
        ->assertJsonPath('status', 'COMPLETED')->assertJsonPath('steps.0.content', 'Halo Sari');
    Http::assertNothingSent();
    // dry run tidak membuat pesan inbox sungguhan
    expect(InAppMessage::count())->toBe(0);
});

it('previews templates safely', function () {
    [$as] = signIn();
    $this->postJson('/console/api/templates/preview', ['template' => 'Hi {{ subscriber.first_name|upper }} {{ payload.n }}', 'payload' => ['n' => 5]])
        ->assertOk()->assertJsonPath('output', 'Hi BUDI 5');
    $this->postJson('/console/api/templates/preview', ['template' => '{{ system("ls") }}'])->assertOk()->assertJsonPath('errors.0', fn ($e) => str_contains($e, 'tidak valid'));
});

it('saves provider credentials encrypted and never echoes secrets', function () {
    [$as] = signIn();
    $this->put('/console/channels/WHATSAPP', ['name' => 'WA', 'status' => 'active', 'endpoint_url' => 'https://wa.test', 'api_key' => 'super-secret-1234', 'session_id' => 'main'])
        ->assertRedirect();

    $raw = DB::table('channel_providers')->value('credentials');
    expect($raw)->not->toContain('super-secret');

    $html = $this->get('/console/channels/WHATSAPP')->assertOk()->getContent();
    expect($html)->not->toContain('super-secret-1234')->and($html)->toContain('••••1234');

    // kosong = pertahankan nilai lama
    $this->put('/console/channels/WHATSAPP', ['name' => 'WA2', 'status' => 'active', 'endpoint_url' => 'https://wa.test', 'api_key' => '', 'session_id' => 'main'])->assertRedirect();
    expect(ChannelProvider::first()->credentials['api_key'])->toBe('super-secret-1234');
});

it('protects the last owner and limits role assignment', function () {
    [$as, $t] = signIn();
    $member = Member::where('user_id', $t['user']->id)->first();
    $this->put("/console/settings/team/{$member->id}", ['role' => 'ADMIN'])->assertRedirect()->assertSessionHas('error');
    $this->delete("/console/settings/team/{$member->id}")->assertRedirect()->assertSessionHas('error');
    expect($member->fresh()->role)->toBe(MemberRole::OWNER);

    // ADMIN tidak boleh menetapkan OWNER
    [$as2, $t2] = signIn(MemberRole::ADMIN);
    $this->post('/console/settings/team/invite', ['email' => 'x@example.com', 'role' => 'OWNER'])->assertForbidden();
});

it('invites a user who joins the existing workspace instead of creating a new one', function () {
    Mail::fake();
    [$as, $t] = signIn();
    $ws = $t['env']->workspace;
    $this->post('/console/settings/team/invite', ['email' => 'newdev@example.com', 'role' => 'DEVELOPER'])->assertRedirect();
    expect(Invitation::count())->toBe(1);

    $token = app(InvitationService::class)->invite($ws, 'newdev@example.com', MemberRole::DEVELOPER);
    auth()->logout();

    $this->get("/invitations/$token")->assertRedirect('/register');
    $this->post('/register', ['name' => 'New Dev', 'email' => 'newdev@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])
        ->assertRedirect('/console');

    $user = User::where('email', 'newdev@example.com')->first();
    expect($user->memberships()->count())->toBe(1)
        ->and($user->currentMembership()->workspace_id)->toBe($ws->id)
        ->and($user->currentMembership()->role)->toBe(MemberRole::DEVELOPER);
});

it('rejects invitations for a different email', function () {
    Mail::fake();
    $t = makeTenant();
    $token = app(InvitationService::class)->invite($t['env']->workspace, 'invited@example.com', MemberRole::VIEWER);
    $this->get("/invitations/$token");
    $this->post('/register', ['name' => 'Other', 'email' => 'other@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])
        ->assertSessionHasErrors('email');
});

it('keeps environment data isolated when switching in the console', function () {
    [$as, $t] = signIn();
    makeProvider(ChannelType::WHATSAPP);
    makeWorkflow([['channel' => 'WHATSAPP']]);

    $prod = $t['env']->workspace->environments()->where('type', 'PRODUCTION')->first();
    $this->post('/console/environment', ['environment_id' => $prod->id])->assertRedirect();
    $this->get('/console/workflows')->assertOk()->assertSee('PRODUCTION')->assertSee('Belum ada workflow');
});

it('ships a persistent light/dark theme toggle on every layout and no hero graffiti', function () {
    $landing = $this->get('/')->assertOk()->getContent();
    expect($landing)->toContain('notifora_theme')->and($landing)->toContain('Beralih ke mode')
        ->and($landing)->not->toContain('url(#graffiti)');

    $this->get('/login')->assertOk()->assertSee('notifora_theme', false)->assertSee('Beralih ke mode');

    [$as] = signIn();
    $this->get('/console')->assertOk()->assertSee('notifora_theme', false)->assertSee('Beralih ke mode');
});

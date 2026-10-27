<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberRole;
use App\Http\Controllers\Controller;
use App\Mail\Mails;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ChannelProvider;
use App\Models\Environment;
use App\Models\Member;
use App\Models\NotificationEvent;
use App\Models\Payment;
use App\Models\PlatformAuditLog;
use App\Models\Subscriber;
use App\Models\UsageCounter;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\AdminAudit;
use App\Services\PlanCatalog;
use App\Services\SystemNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function __construct(private SystemNotifier $notifier) {}

    public function index(Request $request)
    {
        $q = Workspace::query()->with('owner:id,name,email')->withCount('members');

        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('slug', 'like', "%$s%")->orWhere('id', $s)
                ->orWhere('billing_email', 'like', "%$s%")
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%")));
        }
        if ($plan = $request->query('plan')) {
            $q->where('plan', $plan);
        }
        match ($request->query('status')) {
            'suspended' => $q->whereNotNull('suspended_at'),
            'active' => $q->whereNull('suspended_at'),
            'grace' => $q->where('plan', '!=', 'free')->where('plan_expires_at', '<', now()),
            'expiring' => $q->where('plan', '!=', 'free')->whereBetween('plan_expires_at', [now(), now()->addDays(7)]),
            default => null,
        };
        $sort = in_array($request->query('sort'), ['created_at', 'name', 'plan_expires_at'], true) ? $request->query('sort') : 'created_at';
        $q->orderBy($sort, $sort === 'name' ? 'asc' : 'desc');

        $tenants = $q->paginate(25)->withQueryString();

        $events = UsageCounter::query()
            ->join('environments', 'environments.id', '=', 'usage_counters.environment_id')
            ->whereIn('environments.workspace_id', $tenants->pluck('id'))
            ->whereBetween('usage_counters.date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->groupBy('environments.workspace_id')
            ->selectRaw('environments.workspace_id as wid, sum(usage_counters.events_received) as c')->pluck('c', 'wid');

        return view('admin.tenants.index', ['tenants' => $tenants, 'events' => $events, 'plans' => PlanCatalog::all()]);
    }

    public function show(Workspace $workspace)
    {
        $envIds = $workspace->environments()->pluck('id');
        $month = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        return view('admin.tenants.show', [
            'ws' => $workspace->load('owner:id,name,email'),
            'members' => Member::with('user')->where('workspace_id', $workspace->id)->oldest()->get(),
            'envs' => Environment::whereIn('id', $envIds)->get(),
            'keys' => ApiKey::whereIn('environment_id', $envIds)->with('environment:id,type')->latest()->get(),
            'payments' => Payment::where('workspace_id', $workspace->id)->latest()->limit(10)->get(),
            'providers' => ChannelProvider::withoutGlobalScopes()->whereIn('environment_id', $envIds)->get(),
            'stats' => [
                'workflows' => Workflow::withoutGlobalScopes()->whereIn('environment_id', $envIds)->count(),
                'subscribers' => Subscriber::withoutGlobalScopes()->whereIn('environment_id', $envIds)->count(),
                'webhooks' => WebhookSubscription::withoutGlobalScopes()->whereIn('environment_id', $envIds)->count(),
                'events_month' => (int) UsageCounter::whereIn('environment_id', $envIds)->whereBetween('date', $month)->sum('events_received'),
                'failed_month' => (int) UsageCounter::whereIn('environment_id', $envIds)->whereBetween('date', $month)->sum('messages_failed'),
                'events_total' => NotificationEvent::withoutGlobalScopes()->whereIn('environment_id', $envIds)->count(),
            ],
            'tenantAudit' => AuditLog::with('user:id,name')->where('workspace_id', $workspace->id)->latest('id')->limit(12)->get(),
            'adminAudit' => PlatformAuditLog::with('admin:id,name')->where('workspace_id', $workspace->id)->latest('id')->limit(12)->get(),
            'plans' => PlanCatalog::all(),
            'reasons' => config('admin.suspend_reasons'),
        ]);
    }

    public function suspend(Request $request, Workspace $workspace)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'notify' => ['nullable', 'boolean']]);
        $workspace->update(['suspended_at' => now(), 'suspended_reason' => $data['reason']]);
        $this->forgetKeyCache($workspace);
        AdminAudit::record('tenant.suspended', 'workspace', $workspace->id, $workspace->id, ['reason' => $data['reason']]);
        if ($request->boolean('notify', true)) {
            $this->notifier->deliver($workspace->adminEmails(), Mails::workspaceSuspended($workspace, $data['reason']), $workspace->id);
        }

        return back()->with('success', 'Workspace ditangguhkan. API key ditolak dan Console dikunci.');
    }

    public function unsuspend(Request $request, Workspace $workspace)
    {
        $workspace->update(['suspended_at' => null, 'suspended_reason' => null]);
        $this->forgetKeyCache($workspace);
        AdminAudit::record('tenant.unsuspended', 'workspace', $workspace->id, $workspace->id);
        $this->notifier->deliver($workspace->adminEmails(), Mails::workspaceReinstated($workspace), $workspace->id);

        return back()->with('success', 'Penangguhan dicabut.');
    }

    /** Beri / ubah plan secara manual (tanpa pembayaran). */
    public function plan(Request $request, Workspace $workspace)
    {
        $keys = array_merge([PlanCatalog::FREE], array_keys(PlanCatalog::all()));
        $data = $request->validate([
            'plan' => ['required', Rule::in($keys)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'note' => ['required', 'string', 'max:255'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $before = ['plan' => $workspace->plan, 'expires_at' => $workspace->plan_expires_at?->toIso8601String()];
        $isFree = $data['plan'] === PlanCatalog::FREE;
        $workspace->update([
            'plan' => $data['plan'],
            'plan_started_at' => $isFree ? null : now(),
            'plan_expires_at' => $isFree ? null : ($data['expires_at'] ?? null),
        ]);
        AdminAudit::record('tenant.plan_set', 'workspace', $workspace->id, $workspace->id, ['before' => $before, 'after' => ['plan' => $data['plan'], 'expires_at' => $workspace->plan_expires_at?->toIso8601String()], 'note' => $data['note']]);

        if (! $isFree && $request->boolean('notify')) {
            $label = PlanCatalog::all()[$data['plan']]['label'] ?? $data['plan'];
            $this->notifier->deliver($workspace->adminEmails(), Mails::planGranted($workspace, $label, $workspace->plan_expires_at), $workspace->id);
        }

        return back()->with('success', 'Plan diperbarui.');
    }

    public function limits(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'events' => ['nullable', 'integer', 'min:1'], 'rpm' => ['nullable', 'integer', 'min:1'], 'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);
        $limits = array_filter($data, fn ($v) => $v !== null);
        $workspace->update(['custom_limits' => $limits ?: null]);
        AdminAudit::record('tenant.limits_set', 'workspace', $workspace->id, $workspace->id, ['limits' => $limits]);

        return back()->with('success', $limits ? 'Batas kustom disimpan (menimpa plan).' : 'Batas kustom dihapus; kembali mengikuti plan.');
    }

    public function notes(Request $request, Workspace $workspace)
    {
        $workspace->update(['admin_notes' => $request->validate(['notes' => ['nullable', 'string', 'max:5000']])['notes']]);
        AdminAudit::record('tenant.notes_updated', 'workspace', $workspace->id);

        return back()->with('success', 'Catatan internal disimpan (tidak terlihat pelanggan).');
    }

    /** Pindahkan kepemilikan: anggota terpilih jadi OWNER, owner lama diturunkan ke ADMIN. */
    public function makeOwner(Workspace $workspace, Member $member)
    {
        abort_unless($member->workspace_id === $workspace->id, 404);
        DB::transaction(function () use ($workspace, $member) {
            Member::where('workspace_id', $workspace->id)->where('role', MemberRole::OWNER->value)->update(['role' => MemberRole::ADMIN->value]);
            $member->update(['role' => MemberRole::OWNER]);
            $workspace->update(['owner_id' => $member->user_id]);
        });
        AdminAudit::record('tenant.owner_transferred', 'user', (string) $member->user_id, $workspace->id);

        return back()->with('success', 'Kepemilikan dipindahkan.');
    }

    public function removeMember(Workspace $workspace, Member $member)
    {
        abort_unless($member->workspace_id === $workspace->id, 404);
        if ($member->role === MemberRole::OWNER && Member::where('workspace_id', $workspace->id)->where('role', MemberRole::OWNER->value)->count() <= 1) {
            return back()->with('error', 'Owner terakhir tidak dapat dihapus. Pindahkan kepemilikan dulu.');
        }
        AdminAudit::record('tenant.member_removed', 'user', (string) $member->user_id, $workspace->id);
        $member->delete();

        return back()->with('success', 'Anggota dihapus dari workspace.');
    }

    /** Masuk sebagai pengguna untuk membantu support. Tercatat di audit platform & workspace. */
    public function impersonate(Request $request, Workspace $workspace, User $user)
    {
        abort_unless(Member::where('workspace_id', $workspace->id)->where('user_id', $user->id)->exists(), 404);
        if ($user->isPlatformAdmin()) {
            return back()->with('error', 'Tidak dapat impersonasi sesama operator platform.');
        }

        AdminAudit::record('impersonation.started', 'user', (string) $user->id, $workspace->id, ['email' => $user->email]);
        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->forget('environment_id');
        auth()->login($user);

        return redirect()->route('console.dashboard');
    }

    public function revokeKeys(Workspace $workspace)
    {
        $keys = ApiKey::whereIn('environment_id', $workspace->environments()->pluck('id'))->whereNull('revoked_at')->get();
        foreach ($keys as $k) {
            $k->update(['revoked_at' => now()]);
            Cache::forget('apikey:'.$k->key_hash);
        }
        AdminAudit::record('tenant.keys_revoked', 'workspace', $workspace->id, $workspace->id, ['count' => $keys->count()]);

        return back()->with('success', $keys->count().' API key dicabut.');
    }

    /** Ekspor data workspace (konfigurasi & ringkasan, tanpa kredensial) sebagai JSON. */
    public function export(Workspace $workspace)
    {
        $envIds = $workspace->environments()->pluck('id');
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'workspace' => $workspace->only(['id', 'name', 'slug', 'plan', 'plan_expires_at', 'billing_email', 'created_at']),
            'members' => Member::with('user:id,name,email')->where('workspace_id', $workspace->id)->get()->map(fn ($m) => ['name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->value]),
            'environments' => Environment::whereIn('id', $envIds)->get(['id', 'type', 'name']),
            'workflows' => Workflow::withoutGlobalScopes()->whereIn('environment_id', $envIds)->with('steps.template')->get(),
            'providers' => ChannelProvider::withoutGlobalScopes()->whereIn('environment_id', $envIds)->get(['id', 'channel', 'provider', 'name', 'status']),
            'webhooks' => WebhookSubscription::withoutGlobalScopes()->whereIn('environment_id', $envIds)->get(['id', 'url', 'events', 'status']),
            'subscribers_total' => Subscriber::withoutGlobalScopes()->whereIn('environment_id', $envIds)->count(),
            'payments' => Payment::where('workspace_id', $workspace->id)->get(['number', 'plan', 'months', 'amount', 'status', 'paid_at']),
        ];
        AdminAudit::record('tenant.exported', 'workspace', $workspace->id, $workspace->id);

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="notifora-export-'.$workspace->slug.'-'.now()->format('Ymd').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Hapus permanen workspace beserta seluruh datanya (butuh ketik slug). */
    public function destroy(Request $request, Workspace $workspace)
    {
        $request->validate(['confirm' => ['required', Rule::in([$workspace->slug])]], ['confirm.in' => 'Ketik slug workspace dengan tepat untuk konfirmasi.']);
        $this->forgetKeyCache($workspace);
        AdminAudit::record('tenant.deleted', 'workspace', $workspace->id, null, ['name' => $workspace->name, 'slug' => $workspace->slug, 'owner' => $workspace->owner?->email]);
        $workspace->delete();

        return redirect()->route('admin.tenants.index')->with('success', 'Workspace dan seluruh datanya dihapus permanen.');
    }

    private function forgetKeyCache(Workspace $workspace): void
    {
        ApiKey::whereIn('environment_id', $workspace->environments()->pluck('id'))->pluck('key_hash')
            ->each(fn ($h) => Cache::forget("apikey:$h"));
    }
}

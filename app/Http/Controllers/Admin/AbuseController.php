<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedEntity;
use App\Models\UsageCounter;
use App\Models\Workspace;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AbuseController extends Controller
{
    public function index()
    {
        // Tenant dengan rasio gagal tinggi 7 hari (kandidat spam / konfigurasi rusak)
        $watch = UsageCounter::query()
            ->join('environments', 'environments.id', '=', 'usage_counters.environment_id')
            ->join('workspaces', 'workspaces.id', '=', 'environments.workspace_id')
            ->where('usage_counters.date', '>=', now()->subDays(7)->toDateString())
            ->groupBy('workspaces.id', 'workspaces.name', 'workspaces.plan', 'workspaces.suspended_at')
            ->selectRaw('workspaces.id, workspaces.name, workspaces.plan, workspaces.suspended_at, sum(usage_counters.messages_sent) as sent, sum(usage_counters.messages_failed) as failed, sum(usage_counters.events_received) as events')
            ->havingRaw('sum(usage_counters.messages_failed) > 0')
            ->orderByRaw('sum(usage_counters.messages_failed) desc')->limit(15)->get();

        return view('admin.abuse.index', [
            'blocks' => BlockedEntity::with('creator')->latest()->get(),
            'suspended' => Workspace::whereNotNull('suspended_at')->latest('suspended_at')->get(),
            'watch' => $watch,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(['ip', 'email_domain'])],
            'value' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $value = strtolower(trim($d['value']));
        if ($d['type'] === 'ip' && ! filter_var($value, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['value' => 'Alamat IP tidak valid.']);
        }
        if ($d['type'] === 'email_domain') {
            $value = ltrim($value, '@');
            if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $value)) {
                throw ValidationException::withMessages(['value' => 'Domain tidak valid (contoh: contoh.com).']);
            }
        }
        if ($d['type'] === 'ip' && $value === $request->ip()) {
            throw ValidationException::withMessages(['value' => 'Anda tidak dapat memblokir IP Anda sendiri.']);
        }

        $block = BlockedEntity::updateOrCreate(
            ['type' => $d['type'], 'value' => $value],
            ['reason' => $d['reason'] ?? null, 'expires_at' => $d['expires_at'] ?? null, 'created_by' => $request->user()->id],
        );
        AdminAudit::record('abuse.blocked', $d['type'], $value, null, ['reason' => $d['reason'] ?? null]);

        return back()->with('success', "Diblokir: {$block->value}");
    }

    public function destroy(BlockedEntity $block)
    {
        AdminAudit::record('abuse.unblocked', $block->type, $block->value);
        $block->delete();

        return back()->with('success', 'Blokir dicabut.');
    }
}

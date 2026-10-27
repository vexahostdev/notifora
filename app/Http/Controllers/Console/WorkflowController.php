<?php

namespace App\Http\Controllers\Console;

use App\Enums\ChannelType;
use App\Enums\StepType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChannelProvider;
use App\Models\NotificationEvent;
use App\Models\StepTemplate;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\TemplateCompiler;
use App\Services\WorkflowPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        $q = Workflow::with('publishedVersion')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('trigger_key', 'like', "%$s%"));
        }
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }

        $runs = NotificationEvent::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('workflow_id, count(*) as c')->groupBy('workflow_id')->pluck('c', 'workflow_id');

        return view('console.workflows.index', ['workflows' => $q->get(), 'runs' => $runs]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_\-]*$/',
                Rule::unique('workflows', 'trigger_key')->where('environment_id', app('current.environment')->id)],
            'priority' => ['required', Rule::in(['NORMAL', 'HIGH'])],
        ], ['trigger_key.regex' => 'Trigger key hanya boleh huruf kecil, angka, - dan _.']);

        $workflow = Workflow::create([...$data, 'status' => 'DRAFT']);
        AuditLog::record(app('current.workspace')->id, 'workflow.created', 'workflow', $workflow->id);

        return redirect()->route('console.workflows.edit', $workflow)->with('success', 'Workflow dibuat. Tambahkan step lalu publish.');
    }

    public function edit(Workflow $workflow)
    {
        $workflow->load('publishedVersion');

        return view('console.workflows.edit', [
            'workflow' => $workflow,
            'steps' => self::editorSteps($workflow),
            'providers' => ChannelProvider::get(['id', 'channel', 'name'])->map(fn ($p) => ['id' => $p->id, 'channel' => $p->channel->value, 'name' => $p->name]),
            'channels' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], ChannelType::cases()),
            'samplePayload' => NotificationEvent::where('workflow_id', $workflow->id)->latest('id')->value('payload') ?? ['invoiceId' => 'INV-001', 'amount' => 450000],
        ]);
    }

    /** Simpan draft (JSON dari editor). */
    public function update(Request $request, Workflow $workflow, TemplateCompiler $templates)
    {
        $max = config('notifora.limits.max_steps');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'priority' => ['required', Rule::in(['NORMAL', 'HIGH'])],
            'is_critical' => ['boolean'],
            'steps' => ['present', 'array', "max:$max"],
            'steps.*.uid' => ['required', 'string', 'max:64'],
            'steps.*.type' => ['required', Rule::enum(StepType::class)],
            'steps.*.name' => ['required', 'string', 'max:120'],
            'steps.*.channel' => ['nullable', Rule::enum(ChannelType::class)],
            'steps.*.provider_id' => ['nullable', 'string'],
            'steps.*.config' => ['nullable', 'array'],
            'steps.*.template' => ['nullable', 'array'],
            'steps.*.template.subject' => ['nullable', 'string', 'max:500'],
            'steps.*.template.body' => ['nullable', 'string', 'max:'.TemplateCompiler::MAX_TEMPLATE],
            'steps.*.template.body_html' => ['nullable', 'string', 'max:'.TemplateCompiler::MAX_TEMPLATE],
            'steps.*.fallback_uid' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($workflow, $data) {
            $workflow->update([
                'name' => $data['name'],
                'priority' => $data['priority'],
                'is_critical' => (bool) ($data['is_critical'] ?? false),
            ]);

            $existing = $workflow->steps()->pluck('id')->all();
            $map = [];
            foreach ($data['steps'] as $s) {
                $map[$s['uid']] = in_array($s['uid'], $existing, true) ? $s['uid'] : (string) Str::uuid();
            }

            // Hindari bentrok unique(workflow_id, step_order) saat urutan berubah
            WorkflowStep::where('workflow_id', $workflow->id)->increment('step_order', 1000);
            WorkflowStep::where('workflow_id', $workflow->id)->whereNotIn('id', array_values($map))->delete();

            foreach ($data['steps'] as $i => $s) {
                $isChannel = $s['type'] === StepType::CHANNEL->value;
                $fallback = ($s['fallback_uid'] ?? null) && isset($map[$s['fallback_uid']]) && $map[$s['fallback_uid']] !== $map[$s['uid']]
                    ? $map[$s['fallback_uid']] : null;

                $step = WorkflowStep::updateOrCreate(['id' => $map[$s['uid']], 'workflow_id' => $workflow->id], [
                    'step_order' => $i + 1,
                    'type' => $s['type'],
                    'name' => $s['name'],
                    'channel' => $isChannel ? ($s['channel'] ?? null) : null,
                    'channel_provider_id' => $isChannel ? ($s['provider_id'] ?: null) : null,
                    'config' => $isChannel ? null : ($s['config'] ?? null),
                    'fallback_step_id' => $isChannel ? $fallback : null,
                ]);

                if ($isChannel) {
                    StepTemplate::updateOrCreate(['workflow_step_id' => $step->id, 'locale' => 'id'], [
                        'subject' => $s['template']['subject'] ?? null,
                        'body' => $s['template']['body'] ?? '',
                        'body_html' => $s['template']['body_html'] ?? null,
                    ]);
                } else {
                    StepTemplate::where('workflow_step_id', $step->id)->delete();
                }
            }
        });

        return response()->json(['ok' => true, 'steps' => self::editorSteps($workflow->fresh())]);
    }

    public function publish(Workflow $workflow, WorkflowPublisher $publisher)
    {
        $errors = $publisher->validate($workflow);
        if ($errors) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }
        $version = $publisher->publish($workflow, auth()->id());
        AuditLog::record(app('current.workspace')->id, 'workflow.published', 'workflow', $workflow->id, ['version' => $version->version]);

        return response()->json(['ok' => true, 'version' => $version->version]);
    }

    public function archive(Workflow $workflow)
    {
        $workflow->update(['status' => $workflow->status === 'ARCHIVED' ? 'DRAFT' : 'ARCHIVED']);

        return back()->with('success', 'Status workflow diperbarui.');
    }

    public function toggle(Workflow $workflow)
    {
        if (! $workflow->published_version_id) {
            return back()->with('error', 'Workflow belum pernah dipublish.');
        }
        $workflow->update(['status' => $workflow->status === 'ACTIVE' ? 'DRAFT' : 'ACTIVE']);

        return back()->with('success', $workflow->status === 'ACTIVE' ? 'Workflow diaktifkan.' : 'Workflow dinonaktifkan.');
    }

    public function destroy(Workflow $workflow)
    {
        AuditLog::record(app('current.workspace')->id, 'workflow.deleted', 'workflow', $workflow->id, ['trigger_key' => $workflow->trigger_key]);
        $workflow->delete();

        return redirect()->route('console.workflows.index')->with('success', 'Workflow dihapus.');
    }

    public static function editorSteps(Workflow $workflow): array
    {
        return $workflow->steps()->with('template')->get()->map(fn ($s) => [
            'uid' => $s->id,
            'type' => $s->type->value,
            'name' => $s->name,
            'channel' => $s->channel?->value,
            'provider_id' => $s->channel_provider_id,
            'config' => $s->config ?? ($s->type === StepType::DELAY ? ['seconds' => 60] : ($s->type === StepType::FILTER ? ['rules' => []] : (object) [])),
            'template' => ['subject' => $s->template?->subject ?? '', 'body' => $s->template?->body ?? '', 'body_html' => $s->template?->body_html ?? ''],
            'fallback_uid' => $s->fallback_step_id,
        ])->values()->all();
    }
}

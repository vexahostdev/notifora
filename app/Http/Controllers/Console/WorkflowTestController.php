<?php

namespace App\Http\Controllers\Console;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\NotificationEvent;
use App\Models\Subscriber;
use App\Models\Workflow;
use App\Services\EventIngestor;
use App\Services\TemplateCompiler;
use Illuminate\Http\Request;

class WorkflowTestController extends Controller
{
    public function show(Workflow $workflow)
    {
        return view('console.workflows.test', [
            'workflow' => $workflow,
            'subscribers' => Subscriber::latest()->limit(50)->get(['id', 'external_id', 'first_name', 'email', 'phone']),
        ]);
    }

    /** Kirim event uji. Default dry-run: hanya render, tidak memanggil provider. */
    public function send(Request $request, Workflow $workflow, EventIngestor $ingestor)
    {
        $data = $request->validate([
            'to' => ['required', 'array'],
            'to.subscriberId' => ['required', 'string', 'max:191'],
            'to.email' => ['nullable', 'email'],
            'to.phone' => ['nullable', 'string', 'max:32'],
            'to.telegramChatId' => ['nullable', 'string', 'max:64'],
            'to.firstName' => ['nullable', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
            'real' => ['boolean'],
        ]);

        try {
            $event = $ingestor->ingest(
                app('current.environment'),
                ['workflow' => $workflow->trigger_key, 'to' => $data['to'], 'payload' => $data['payload'] ?? []],
                source: 'console_test',
                dryRun: ! ($data['real'] ?? false),
            );
        } catch (ApiException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], $e->status);
        }

        return response()->json(['ok' => true, 'event_id' => $event->id]);
    }

    public function status(string $eventId)
    {
        $event = NotificationEvent::with('execution.steps.messageLog', 'execution.event.version')->findOrFail($eventId);
        $exec = $event->execution;

        return response()->json([
            'status' => $exec?->status->value ?? 'QUEUED',
            'final' => $exec?->status->isFinal() ?? false,
            'steps' => $exec ? $exec->steps->map(fn ($s) => [
                'order' => $s->step_order,
                'name' => $s->definition()['name'] ?? '-',
                'type' => $s->type->value,
                'status' => $s->status->value,
                'skip_reason' => $s->skip_reason,
                'subject' => $s->rendered_subject,
                'content' => $s->rendered_content,
                'message_status' => $s->messageLog?->status->value,
                'error' => $s->messageLog?->error_code,
            ])->values() : [],
        ]);
    }

    /** Preview template (dipakai editor, debounce di client). */
    public function preview(Request $request, TemplateCompiler $templates)
    {
        $data = $request->validate([
            'template' => ['nullable', 'string', 'max:'.TemplateCompiler::MAX_TEMPLATE],
            'payload' => ['nullable', 'array'],
            'html' => ['boolean'],
        ]);

        $tpl = (string) ($data['template'] ?? '');
        $warnings = [];
        $ctx = [
            'payload' => $data['payload'] ?? [],
            'subscriber' => ['first_name' => 'Budi', 'last_name' => 'Santoso', 'full_name' => 'Budi Santoso', 'email' => 'budi@example.com', 'phone' => '+6281234567890', 'timezone' => 'Asia/Jakarta'],
            'event' => ['id' => '01SAMPLE', 'created_at' => now()->toIso8601String()],
            'workspace' => ['name' => app('current.workspace')->name],
        ];

        return response()->json([
            'errors' => $templates->validate($tpl),
            'output' => $templates->render($tpl, $ctx, (bool) ($data['html'] ?? false), $warnings),
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }
}

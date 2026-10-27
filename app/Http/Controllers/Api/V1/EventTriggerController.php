<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExecutionStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TriggerEventRequest;
use App\Models\NotificationEvent;
use App\Services\EventIngestor;
use App\Services\WorkflowEngine;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventTriggerController extends Controller
{
    public function store(TriggerEventRequest $request, EventIngestor $ingestor)
    {
        $event = $ingestor->ingest(
            app('current.environment'),
            $request->validated(),
            $request->header('X-Idempotency-Key'),
            requestHash: hash('sha256', $request->getContent()),
        );

        return ApiResponse::ok([
            'event_id' => $event->id,
            'status' => 'QUEUED',
            'workflow' => $request->input('workflow'),
        ], status: 202);
    }

    public function bulk(Request $request, EventIngestor $ingestor)
    {
        $max = (int) config('notifora.limits.bulk_max');
        $request->validate(['events' => ['required', 'array', 'min:1', 'max:'.$max]]);

        $results = [];
        foreach ($request->input('events') as $i => $item) {
            $item = TriggerEventRequest::normalize(is_array($item) ? $item : []);
            $v = Validator::make($item, TriggerEventRequest::rulesFor());
            if ($v->fails()) {
                $results[] = ['index' => $i, 'status' => 'REJECTED', 'error' => ['code' => 'validation_failed', 'details' => $v->errors()->toArray()]];

                continue;
            }
            try {
                $event = $ingestor->ingest(app('current.environment'), $v->validated(), source: 'api');
                $results[] = ['index' => $i, 'status' => 'QUEUED', 'event_id' => $event->id];
            } catch (ApiException $e) {
                $results[] = ['index' => $i, 'status' => 'REJECTED', 'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]];
            }
        }

        return ApiResponse::ok($results, status: 202);
    }

    public function show(string $id)
    {
        $event = NotificationEvent::with(['workflow', 'execution.steps.messageLog', 'execution.event.version'])->findOrFail($id);
        $exec = $event->execution;

        return ApiResponse::ok([
            'event_id' => $event->id,
            'workflow' => $event->workflow->trigger_key,
            'status' => $exec?->status->value ?? 'QUEUED',
            'created_at' => $event->created_at->toIso8601String(),
            'finished_at' => $exec?->finished_at?->toIso8601String(),
            'steps' => $exec ? $exec->steps->map(fn ($s) => [
                'order' => $s->step_order,
                'name' => $s->definition()['name'] ?? null,
                'type' => $s->type->value,
                'status' => $s->status->value,
                'skip_reason' => $s->skip_reason,
                'message_id' => $s->messageLog?->id,
                'message_status' => $s->messageLog?->status->value,
            ])->values() : [],
        ]);
    }

    public function destroy(string $id, WorkflowEngine $engine)
    {
        $event = NotificationEvent::with('execution')->findOrFail($id);
        $exec = $event->execution;

        if (! $exec || $exec->status->isFinal()) {
            throw new ApiException('validation_failed', 'Eksekusi sudah selesai atau belum dimulai; tidak dapat dibatalkan.', 422);
        }
        $engine->cancel($exec);

        return ApiResponse::ok(['event_id' => $event->id, 'status' => ExecutionStatus::CANCELED->value]);
    }
}

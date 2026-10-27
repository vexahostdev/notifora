<?php

namespace App\Http\Controllers\Console;

use App\Enums\ChannelType;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Workflow;
use Illuminate\Http\Request;

class MessageLogController extends Controller
{
    public function index(Request $request)
    {
        $q = MessageLog::with('workflow:id,name,trigger_key')->latest('id');

        if ($st = $request->query('status')) {
            $q->whereIn('status', (array) $st);
        }
        if ($ch = $request->query('channel')) {
            $q->where('channel_type', $ch);
        }
        if ($wf = $request->query('workflow')) {
            $q->where('workflow_id', $wf);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('external_message_id', $s)->orWhere('id', $s)->orWhere('destination_masked', 'like', "%$s%"));
        }
        $since = match ($request->query('range', '24h')) {
            '1h' => now()->subHour(), '24h' => now()->subDay(), '7d' => now()->subDays(7), '30d' => now()->subDays(30), default => null,
        };
        if ($since) {
            $q->where('created_at', '>=', $since);
        }

        return view('console.logs.index', [
            'logs' => $q->paginate(30)->withQueryString(),
            'statuses' => MessageStatus::cases(),
            'channels' => ChannelType::cases(),
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(string $id)
    {
        $log = MessageLog::with(['attemptsLog', 'stepExecution.execution.event.workflow', 'stepExecution.execution.event.subscriber'])->findOrFail($id);

        return view('console.logs.show', ['log' => $log, 'step' => $log->stepExecution, 'event' => $log->stepExecution?->execution?->event]);
    }
}

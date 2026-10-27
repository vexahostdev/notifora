<?php

namespace App\Http\Controllers\Console;

use App\Enums\ChannelType;
use App\Enums\MemberRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MessageLog;
use App\Models\Subscriber;
use App\Models\SubscriberPreference;
use App\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $q = Subscriber::query()->latest();
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('external_id', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"));
        }
        if ($c = $request->query('channel')) {
            $col = match ($c) {
                'WHATSAPP' => 'phone', 'EMAIL' => 'email', 'TELEGRAM' => 'telegram_chat_id', default => null
            };
            if ($col) {
                $q->whereNotNull($col);
            }
        }

        return view('console.subscribers.index', ['subscribers' => $q->paginate(25)->withQueryString()]);
    }

    public function show(Request $request, Subscriber $subscriber)
    {
        $reveal = $request->boolean('reveal') && app('current.role')->atLeast(MemberRole::ADMIN);
        if ($reveal) {
            AuditLog::record(app('current.workspace')->id, 'subscriber.revealed', 'subscriber', $subscriber->id);
        }

        $prefs = $subscriber->preferences()->get();
        $workflows = Workflow::orderBy('name')->get(['id', 'name', 'trigger_key']);

        return view('console.subscribers.show', [
            'subscriber' => $subscriber,
            'reveal' => $reveal,
            'prefs' => $prefs,
            'workflows' => $workflows,
            'channels' => ChannelType::cases(),
            'logs' => MessageLog::where('subscriber_id', $subscriber->id)->latest('id')->limit(20)->get(),
        ]);
    }

    /** Simpan matrix preferensi. Nilai: on | off | default (hapus override). */
    public function preferences(Request $request, Subscriber $subscriber)
    {
        $data = $request->validate(['pref' => ['required', 'array']]);

        foreach ($data['pref'] as $key => $value) {
            [$wf, $channel] = array_pad(explode('|', (string) $key, 2), 2, null);
            if (! $channel || ! ChannelType::tryFrom($channel) || ! in_array($value, ['on', 'off', 'default'], true)) {
                continue;
            }
            $workflowId = $wf === '*' ? null : Workflow::where('id', $wf)->value('id');
            if ($wf !== '*' && ! $workflowId) {
                continue;
            }
            $where = ['subscriber_id' => $subscriber->id, 'workflow_id' => $workflowId, 'channel' => $channel];
            if ($value === 'default') {
                SubscriberPreference::where($where)->delete();
            } else {
                SubscriberPreference::updateOrCreate($where, ['enabled' => $value === 'on']);
            }
        }

        return back()->with('success', 'Preferensi disimpan.');
    }

    public function destroy(Request $request, Subscriber $subscriber)
    {
        $request->validate(['confirm' => ['required', Rule::in([$subscriber->external_id])]], ['confirm.in' => 'Ketik subscriber ID dengan benar untuk konfirmasi.']);

        MessageLog::where('subscriber_id', $subscriber->id)->update(['subscriber_id' => null]);
        AuditLog::record(app('current.workspace')->id, 'subscriber.erased', 'subscriber', $subscriber->id);
        $subscriber->delete();

        return redirect()->route('console.subscribers.index')->with('success', 'Subscriber dihapus.');
    }
}

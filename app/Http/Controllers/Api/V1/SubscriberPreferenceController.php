<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChannelType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\SubscriberPreference;
use App\Models\Workflow;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriberPreferenceController extends Controller
{
    public function show(string $externalId)
    {
        $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();

        return ApiResponse::ok($this->present($subscriber));
    }

    public function update(Request $request, string $externalId)
    {
        $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();
        $request->validate([
            'preferences' => ['required', 'array', 'max:100'],
            'preferences.*.channel' => ['required', Rule::enum(ChannelType::class)],
            'preferences.*.workflow' => ['nullable', 'string', 'max:100'],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($request->input('preferences') as $pref) {
            $workflowId = null;
            if (! empty($pref['workflow'])) {
                $workflowId = Workflow::where('trigger_key', $pref['workflow'])->value('id')
                    ?? throw new ApiException('not_found', "Workflow '{$pref['workflow']}' tidak ditemukan.", 404);
            }
            SubscriberPreference::updateOrCreate(
                ['subscriber_id' => $subscriber->id, 'workflow_id' => $workflowId, 'channel' => $pref['channel']],
                ['enabled' => (bool) $pref['enabled']],
            );
        }

        return ApiResponse::ok($this->present($subscriber));
    }

    private function present(Subscriber $subscriber): array
    {
        $workflows = Workflow::pluck('trigger_key', 'id');

        return $subscriber->preferences()->get()->map(fn ($p) => [
            'channel' => $p->channel,
            'workflow' => $p->workflow_id ? ($workflows[$p->workflow_id] ?? null) : null,
            'enabled' => $p->enabled,
        ])->values()->all();
    }
}

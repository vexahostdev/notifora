<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Models\Subscriber;
use App\Models\SubscriberPreference;
use App\Models\Workflow;

class PreferenceResolver
{
    /**
     * Global off selalu menang. Workflow kritis (OTP) mengabaikan opt-out per-workflow.
     */
    public function allows(Subscriber $subscriber, Workflow $workflow, ChannelType $channel): bool
    {
        $prefs = SubscriberPreference::where('subscriber_id', $subscriber->id)
            ->where('channel', $channel->value)
            ->get();

        $global = $prefs->first(fn ($p) => $p->workflow_id === null);
        if ($global && ! $global->enabled) {
            return false;
        }
        if ($workflow->is_critical) {
            return true;
        }

        $specific = $prefs->first(fn ($p) => $p->workflow_id === $workflow->id);

        return $specific ? $specific->enabled : true;
    }
}

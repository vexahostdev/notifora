<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\ChannelProvider;
use App\Models\Environment;
use App\Models\MessageLog;
use App\Services\Providers\ProviderFactory;
use App\Services\WebhookDispatcher;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProviderWebhookController extends Controller
{
    /** Status delivery masuk dari provider. Status hanya boleh maju. */
    public function __invoke(Request $request, string $provider, string $token, ProviderFactory $factory, WebhookDispatcher $hooks)
    {
        $cp = ChannelProvider::withoutGlobalScopes()->where('webhook_token', $token)->where('provider', $provider)->first();
        abort_unless($cp, 404);

        return Environment::run($cp->environment_id, function () use ($request, $cp, $factory, $hooks) {
            $update = $factory->make($cp)->parseWebhook($request);
            if (! $update) {
                return ApiResponse::ok(['accepted' => false]);
            }

            $log = MessageLog::where('external_message_id', $update->externalMessageId)->first();
            if (! $log) {
                return ApiResponse::ok(['accepted' => false]);
            }

            $extra = $update->status === MessageStatus::FAILED
                ? ['error_code' => 'provider_reported_failure', 'error_message' => $update->error]
                : [];
            $changed = $log->advanceStatus($update->status, $extra);

            if ($changed) {
                $hooks->dispatch($cp->environment_id, 'message.'.strtolower($update->status->value), [
                    'message_id' => $log->id, 'channel' => $log->channel_type, 'status' => $update->status->value,
                ]);
            }

            return ApiResponse::ok(['accepted' => true, 'updated' => $changed]);
        });
    }
}

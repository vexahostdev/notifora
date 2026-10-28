<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\ProcessWorkflowEventJob;
use App\Models\Environment;
use App\Models\NotificationEvent;
use App\Models\Workflow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EventIngestor
{
    public function __construct(private SubscriberService $subscribers, private UsageMeter $usage, private SystemNotifier $notifier) {}

    /**
     * Terima trigger: validasi workflow, quota, upsert subscriber, simpan event (durable), lalu enqueue.
     * Dipanggil dalam konteks environment aktif.
     *
     * @param  array{workflow:string,to:array|string,payload?:array,overrides?:array}  $input
     */
    public function ingest(Environment $env, array $input, ?string $idempotencyKey = null, string $source = 'api', bool $dryRun = false, ?string $requestHash = null): NotificationEvent
    {
        $workflow = Workflow::where('trigger_key', $input['workflow'])->first();
        if (! $workflow) {
            throw new ApiException('not_found', "Workflow '{$input['workflow']}' tidak ditemukan.", 404);
        }
        if (! $workflow->isActive()) {
            throw new ApiException('workflow_inactive', "Workflow '{$workflow->trigger_key}' belum aktif.", 422);
        }

        $payload = $input['payload'] ?? [];
        if (strlen(json_encode($payload)) > config('notifora.limits.max_payload_bytes')) {
            throw new ApiException('validation_failed', 'Payload melebihi 64 KB.', 422);
        }

        if ($idempotencyKey) {
            $existing = NotificationEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $this->usage->assertWithinQuota($env);

        try {
            $event = DB::transaction(function () use ($env, $input, $workflow, $payload, $idempotencyKey, $source, $dryRun, $requestHash) {
                $subscriber = $this->subscribers->upsert($input['to']);

                return NotificationEvent::create([
                    'environment_id' => $env->id,
                    'workflow_id' => $workflow->id,
                    'workflow_version_id' => $workflow->published_version_id,
                    'subscriber_id' => $subscriber->id,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'payload' => $payload,
                    'overrides' => $input['overrides'] ?? null,
                    'source' => $source,
                    'is_test' => $source !== 'api',
                    'dry_run' => $dryRun,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Balapan idempotency: event sudah dibuat request lain.
            if ($idempotencyKey !== null) {
                $existing = NotificationEvent::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // Pelanggaran unik lain (bukan idempotency) tidak boleh ditelan diam-diam.
            throw $e;
        }

        $this->usage->incrementEvent($env);
        $this->notifier->quotaThreshold($env, $this->usage->lastUsed + 1, $this->usage->limitFor($env));

        $queue = ($workflow->priority === 'HIGH' || $workflow->is_critical)
            ? config('notifora.queues.high')
            : config('notifora.queues.default');

        ProcessWorkflowEventJob::dispatch($event->id, $env->id)->onQueue($queue)->afterCommit();

        return $event;
    }
}
